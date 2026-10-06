<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveManagedCashFlowRequest;
use App\Services\CashFlow\ManagedCashFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * FEA — adjusted/managed cash flow. `show` returns the month's computed +
 * adjusted view; `store` upserts the planned amounts and returns the fresh view.
 */
class ManagedCashFlowController extends Controller
{
    public function __construct(private readonly ManagedCashFlowService $fea)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $period = $data['period'] ?? Carbon::now()->format('Y-m');

        return response()->json($this->fea->get($request->user()->id, $period));
    }

    public function store(SaveManagedCashFlowRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $this->fea->save($request->user()->id, $data['period'], $data['rows']),
        );
    }
}
