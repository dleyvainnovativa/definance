<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportEntriesRequest;
use App\Services\Import\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bulk import of journal entries (paste / CSV). `preview` is a dry run that
 * validates and groups without writing; `store` posts via ImportService
 * (which itself posts through PostingService — the single write path).
 */
class JournalEntryImportController extends Controller
{
    public function __construct(private readonly ImportService $import)
    {
    }

    /** Dry run: group + validate, write nothing. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(ImportEntriesRequest::rowRules());

        return response()->json($this->import->preview($request->user()->id, $data['rows']));
    }

    /** Post the batch according to `mode`. */
    public function store(ImportEntriesRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->import->import($request->user()->id, $data['rows'], $data['mode']);

        // 201 when anything was posted; 422 when the batch was rejected with
        // nothing posted (so the client treats it as a validation failure).
        $status = $result['posted'] > 0 ? 201 : 422;

        return response()->json($result, $status);
    }
}
