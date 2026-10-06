<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveBudgetMonthlyRequest;
use App\Http\Requests\SaveBudgetRequest;
use App\Services\Budget\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Annual budget vs actual. `index` returns the year's budget/actual/variance
 * per income/expense account; `store` upserts the budget and returns it fresh.
 */
class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = $data['year'] ?? (int) Carbon::now()->format('Y');

        return response()->json($this->budgets->annual($request->user()->id, $year));
    }

    public function store(SaveBudgetRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $this->budgets->saveAnnual($request->user()->id, (int) $data['year'], $data['rows']),
        );
    }

    public function monthly(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = $data['year'] ?? (int) Carbon::now()->format('Y');

        return response()->json($this->budgets->monthly($request->user()->id, $year));
    }

    public function storeMonthly(SaveBudgetMonthlyRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $this->budgets->saveMonthly($request->user()->id, (int) $data['year'], $data['rows']),
        );
    }
}
