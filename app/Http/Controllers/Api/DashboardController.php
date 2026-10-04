<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        return response()->json([
            'accounts' => ChartOfAccount::count(),
            'entries_total' => JournalEntry::count(),
            'entries_this_month' => JournalEntry::whereBetween('entry_date', [$monthStart, $monthEnd])->count(),
            'period' => ['from' => $monthStart, 'to' => $monthEnd],
        ]);
    }
}
