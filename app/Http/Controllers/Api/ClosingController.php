<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseYearRequest;
use App\Http\Requests\SaveClosingSettingsRequest;
use App\Services\Closing\ClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cierre de ejercicio (year-end close): year status, close, reopen, and the
 * result-account setting. All accounting is done by ClosingService.
 */
class ClosingController extends Controller
{
    public function __construct(private readonly ClosingService $closing)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->closing->status($request->user()->id));
    }

    public function settings(Request $request): JsonResponse
    {
        return response()->json($this->closing->settings($request->user()->id));
    }

    public function saveSettings(SaveClosingSettingsRequest $request): JsonResponse
    {
        return response()->json($this->closing->saveSettings(
            $request->user()->id,
            (int) $request->validated()['result_account_id'],
        ));
    }

    public function store(CloseYearRequest $request): JsonResponse
    {
        try {
            $result = $this->closing->close($request->user()->id, (int) $request->validated()['year']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result, 201);
    }

    public function destroy(Request $request, int $year): JsonResponse
    {
        try {
            $result = $this->closing->reopen($request->user()->id, $year);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
