<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\ChartOfAccount;
use App\Rules\OwnedAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Chart of accounts CRUD. Every query is tenant-scoped automatically by the
 * global UserScope, so route-model binding and listing only ever see the
 * authenticated user's accounts.
 */
class AccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = ChartOfAccount::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', (string) $request->input('type')))
            ->when($request->filled('postable'), fn ($q) => $q->where('is_postable', $request->boolean('postable')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = (string) $request->input('search');
                $q->where(fn ($q) => $q->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
            })
            ->orderBy('code')
            ->get();

        return AccountResource::collection($accounts);
    }

    /**
     * Suggest the next child code for a parent (SAT código agrupador style,
     * dot-segmented: 100 → 100.01 → 100.01.01). Takes the max existing child
     * segment + 1 (gaps are not reused), zero-padded to 2 digits, and keeps
     * incrementing past any already-taken code. The form pre-fills this; it
     * stays editable and the unique rule is the final guard.
     */
    public function nextCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => ['required', new OwnedAccount(requirePostable: false)],
        ]);

        $userId = $request->user()->id;
        $parent = ChartOfAccount::query()->where('user_id', $userId)->findOrFail($data['parent_id']);
        $prefix = $parent->code.'.';

        $max = 0;
        foreach (ChartOfAccount::query()->where('user_id', $userId)->where('parent_id', $parent->id)->pluck('code') as $code) {
            if (! str_starts_with((string) $code, $prefix)) {
                continue;
            }
            $segment = explode('.', substr((string) $code, strlen($prefix)))[0];
            if (ctype_digit($segment)) {
                $max = max($max, (int) $segment);
            }
        }

        $next = $max + 1;
        do {
            $candidate = $prefix.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
            $next++;
        } while (ChartOfAccount::query()->where('user_id', $userId)->where('code', $candidate)->exists());

        return response()->json(['parent_id' => $parent->id, 'next_code' => $candidate]);
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = ChartOfAccount::create($request->validated());

        return AccountResource::make($account)->response()->setStatusCode(201);
    }

    public function show(ChartOfAccount $account): AccountResource
    {
        $this->authorize('view', $account);

        return AccountResource::make($account);
    }

    public function update(UpdateAccountRequest $request, ChartOfAccount $account): AccountResource
    {
        $this->authorize('update', $account);
        $account->update($request->validated());

        return AccountResource::make($account);
    }

    public function destroy(ChartOfAccount $account): JsonResponse
    {
        $this->authorize('delete', $account);

        if ($account->lines()->exists()) {
            return response()->json(
                ['message' => 'Account has journal entries and cannot be deleted. Deactivate it instead.'],
                409,
            );
        }

        $account->delete();

        return response()->json(status: 204);
    }
}
