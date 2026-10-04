<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\ChartOfAccount;
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
