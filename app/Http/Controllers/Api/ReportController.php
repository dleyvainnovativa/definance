<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Financial statements, all computed by LedgerService (indexed aggregate
 * queries — no SQL views, no PHP row loops over the whole ledger).
 */
class ReportController extends Controller
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    public function trialBalance(Request $request): JsonResponse
    {
        ['from' => $from, 'to' => $to] = $this->period($request);

        return response()->json($this->ledger->trialBalance($request->user()->id, $from, $to));
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        ['from' => $from, 'to' => $to] = $this->period($request);

        return response()->json($this->ledger->incomeStatement($request->user()->id, $from, $to));
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $data = $request->validate(['as_of' => ['required', 'date']]);

        return response()->json($this->ledger->balanceSheet($request->user()->id, $data['as_of']));
    }

    public function cashFlow(Request $request): JsonResponse
    {
        ['from' => $from, 'to' => $to] = $this->period($request);

        return response()->json($this->ledger->cashFlow($request->user()->id, $from, $to));
    }

    public function averages(Request $request): JsonResponse
    {
        ['from' => $from, 'to' => $to] = $this->period($request);

        return response()->json($this->ledger->averages($request->user()->id, $from, $to));
    }

    /** @return array{from:string,to:string} */
    private function period(Request $request): array
    {
        return $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
    }
}
