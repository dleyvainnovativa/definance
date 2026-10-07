<?php

namespace App\Http\Controllers\Api;

use App\Enums\EntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJournalEntryRequest;
use App\Http\Requests\UpdatePostedEntryRequest;
use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Services\Ledger\PostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JournalEntryController extends Controller
{
    public function __construct(private readonly PostingService $posting)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = JournalEntry::query()
            ->with('lines.account')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->date('to')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = (string) $request->input('search');
                $q->where(fn ($q) => $q->where('description', 'like', "%{$s}%")->orWhere('reference', 'like', "%{$s}%"));
            })
            ->when($request->filled('account_id'), fn ($q) => $q->whereHas(
                'lines',
                fn ($q) => $q->where('account_id', $request->integer('account_id'))
            ))
            // Advanced filters: entries touching an account specifically on the
            // debit side or the credit side.
            ->when($request->filled('debit_account_id'), fn ($q) => $q->whereHas(
                'lines',
                fn ($q) => $q->where('account_id', $request->integer('debit_account_id'))->whereNotNull('debit')
            ))
            ->when($request->filled('credit_account_id'), fn ($q) => $q->whereHas(
                'lines',
                fn ($q) => $q->where('account_id', $request->integer('credit_account_id'))->whereNotNull('credit')
            ))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return JournalEntryResource::collection($entries);
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Balance/ownership already validated; PostingService is the final guard.
        $entry = $this->posting->post(
            userId: $request->user()->id,
            entryDate: $data['entry_date'],
            legs: $data['legs'],
            description: $data['description'] ?? null,
            reference: $data['reference'] ?? null,
            status: ($data['status'] ?? 'posted') === 'draft' ? EntryStatus::Draft : EntryStatus::Posted,
        );

        return JournalEntryResource::make($entry->load('lines.account'))
            ->response()->setStatusCode(201);
    }

    public function show(JournalEntry $entry): JournalEntryResource
    {
        $this->authorize('view', $entry);

        return JournalEntryResource::make($entry->load('lines.account'));
    }

    /** Edit a draft entry's legs/header in place (policy: owner + draft). */
    public function update(StoreJournalEntryRequest $request, JournalEntry $entry): JournalEntryResource
    {
        $this->authorize('update', $entry);
        $data = $request->validated();

        $entry = $this->posting->updateDraft(
            entry: $entry,
            entryDate: $data['entry_date'],
            legs: $data['legs'],
            description: $data['description'] ?? null,
            reference: $data['reference'] ?? null,
        );

        return JournalEntryResource::make($entry->load('lines.account'));
    }

    /** Edit a POSTED entry's metadata only (date/description/reference). */
    public function updateMeta(UpdatePostedEntryRequest $request, JournalEntry $entry): JournalEntryResource
    {
        $this->authorize('updateMeta', $entry);
        $data = $request->validated();

        $entry = $this->posting->updatePostedMeta(
            entry: $entry,
            entryDate: $data['entry_date'],
            description: $data['description'] ?? null,
            reference: $data['reference'] ?? null,
        );

        return JournalEntryResource::make($entry->load('lines.account'));
    }

    /** Post a draft entry (draft → posted). */
    public function postDraft(JournalEntry $entry): JournalEntryResource
    {
        $this->authorize('update', $entry);

        $posted = $this->posting->postDraft($entry);

        return JournalEntryResource::make($posted->load('lines.account'));
    }

    public function void(JournalEntry $entry): JournalEntryResource
    {
        $this->authorize('void', $entry);

        $reversing = $this->posting->void($entry);

        return JournalEntryResource::make($reversing->load('lines.account'));
    }
}
