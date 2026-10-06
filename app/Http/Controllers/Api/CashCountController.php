<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCashCountRequest;
use App\Http\Requests\SaveCashCountSettingsRequest;
use App\Services\CashCount\CashCountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Arqueo de caja. `index` previews book balances to count; `store` records the
 * count and posts the adjusting entry; `settings`/`saveSettings` manage the
 * difference account and counted-accounts subset.
 */
class CashCountController extends Controller
{
    public function __construct(private readonly CashCountService $cash)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date']]);
        $date = $data['date'] ?? Carbon::now()->toDateString();

        return response()->json($this->cash->preview($request->user()->id, $date));
    }

    public function store(SaveCashCountRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->cash->save($request->user()->id, $data['date'], $data['counts'], $data['note'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result, $result['posted'] ? 201 : 200);
    }

    public function settings(Request $request): JsonResponse
    {
        return response()->json($this->cash->settings($request->user()->id));
    }

    public function saveSettings(SaveCashCountSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->cash->saveSettings(
            $request->user()->id,
            (int) $data['difference_account_id'],
            $data['counted_account_ids'] ?? null,
        ));
    }
}
