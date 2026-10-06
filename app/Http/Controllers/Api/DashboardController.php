<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Ledger\LedgerService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    private const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public function __construct(private readonly LedgerService $ledger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        return response()->json([
            'accounts' => ChartOfAccount::count(),
            'entries_total' => JournalEntry::count(),
            'entries_this_month' => JournalEntry::whereBetween('entry_date', [$monthStart, $monthEnd])->count(),
            'period' => ['from' => $monthStart, 'to' => $monthEnd],
            'trend' => $this->trend($userId),
            'expense_breakdown' => $this->expenseBreakdown($userId),
        ]);
    }

    /** Net income for each of the last 6 months (oldest first). @return array<int,array<string,mixed>> */
    private function trend(int $userId): array
    {
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = Carbon::now()->subMonths($i);
            $statement = $this->ledger->incomeStatement(
                $userId,
                $m->copy()->startOfMonth()->toDateString(),
                $m->copy()->endOfMonth()->toDateString(),
            );
            $out[] = [
                'month' => $m->format('Y-m'),
                'label' => self::MESES[$m->month - 1],
                'net' => $statement['totals']['net_income'],
            ];
        }

        return $out;
    }

    /** Top expense accounts for the current year (rest folded into "Otros"). @return array<int,array<string,mixed>> */
    private function expenseBreakdown(int $userId, int $top = 6): array
    {
        $statement = $this->ledger->incomeStatement(
            $userId,
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );

        $expenses = collect($statement['expenses'])
            ->filter(fn ($e) => Money::isPositive(Money::of($e['amount'])))
            ->sortByDesc(fn ($e) => (float) $e['amount'])
            ->values();

        $head = $expenses->take($top)
            ->map(fn ($e) => ['name' => $e['name'], 'amount' => $e['amount']])
            ->all();

        $rest = $expenses->slice($top);
        if ($rest->isNotEmpty()) {
            $head[] = ['name' => 'Otros', 'amount' => Money::sum($rest->pluck('amount'))];
        }

        return $head;
    }
}
