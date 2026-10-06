<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\JournalEntryImportController;
use App\Http\Controllers\Api\ManagedCashFlowController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TaxController;
use Illuminate\Support\Facades\Route;

// Public health check.
Route::get('/ping', fn () => response()->json(['status' => 'ok']))->name('api.ping');

Route::middleware('firebase.jwt')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

    // Chart of accounts CRUD.
    Route::apiResource('accounts', AccountController::class);

    // Journal entries.
    Route::get('/entries', [JournalEntryController::class, 'index'])->name('entries.index');
    Route::post('/entries', [JournalEntryController::class, 'store'])->name('entries.store');
    // Bulk import — defined before the {entry} routes so the static path wins.
    Route::post('/entries/import/preview', [JournalEntryImportController::class, 'preview'])->name('entries.import.preview');
    Route::post('/entries/import', [JournalEntryImportController::class, 'store'])->name('entries.import');
    Route::get('/entries/{entry}', [JournalEntryController::class, 'show'])->name('entries.show');
    Route::post('/entries/{entry}/void', [JournalEntryController::class, 'void'])->name('entries.void');

    // Taxes (IVA).
    Route::get('/taxes', [TaxController::class, 'config'])->name('taxes.config');

    // Reports.
    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])->name('reports.income-statement');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('/reports/iva', [TaxController::class, 'iva'])->name('reports.iva');

    // FEA — managed / adjusted cash flow.
    Route::get('/managed-cash-flow', [ManagedCashFlowController::class, 'show'])->name('managed-cash-flow.show');
    Route::post('/managed-cash-flow', [ManagedCashFlowController::class, 'store'])->name('managed-cash-flow.store');

    // Presupuesto (budget) — annual.
    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');

    // Profile.
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
});
