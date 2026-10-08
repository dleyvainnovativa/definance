<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CashCountController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\JournalEntryImportController;
use App\Http\Controllers\Api\LabelController;
use App\Http\Controllers\Api\ManagedCashFlowController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TaxController;
use Illuminate\Support\Facades\Route;

// Public health check.
Route::get('/ping', fn () => response()->json(['status' => 'ok']))->name('api.ping');

Route::middleware('firebase.jwt')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

    // Chart of accounts CRUD. Static helper route before the resource so it
    // isn't captured by /accounts/{account}.
    Route::get('/accounts/next-code', [AccountController::class, 'nextCode'])->name('accounts.next-code');
    // Labels-only update — allowed even on structurally-locked standard accounts.
    Route::put('/accounts/{account}/labels', [AccountController::class, 'updateLabels'])->name('accounts.labels.update');
    Route::apiResource('accounts', AccountController::class);

    // Journal entries.
    Route::get('/entries', [JournalEntryController::class, 'index'])->name('entries.index');
    Route::post('/entries', [JournalEntryController::class, 'store'])->name('entries.store');
    // Bulk import — defined before the {entry} routes so the static path wins.
    Route::post('/entries/import/preview', [JournalEntryImportController::class, 'preview'])->name('entries.import.preview');
    Route::post('/entries/import', [JournalEntryImportController::class, 'store'])->name('entries.import');

    // Arqueo de caja (cash count).
    Route::get('/cash-count/settings', [CashCountController::class, 'settings'])->name('cash-count.settings');
    Route::put('/cash-count/settings', [CashCountController::class, 'saveSettings'])->name('cash-count.settings.save');
    Route::get('/cash-count', [CashCountController::class, 'index'])->name('cash-count.index');
    Route::post('/cash-count', [CashCountController::class, 'store'])->name('cash-count.store');
    Route::get('/entries/{entry}', [JournalEntryController::class, 'show'])->name('entries.show');
    Route::put('/entries/{entry}', [JournalEntryController::class, 'update'])->name('entries.update');
    Route::patch('/entries/{entry}/meta', [JournalEntryController::class, 'updateMeta'])->name('entries.meta');
    Route::post('/entries/{entry}/post', [JournalEntryController::class, 'postDraft'])->name('entries.post');
    Route::post('/entries/{entry}/void', [JournalEntryController::class, 'void'])->name('entries.void');

    // Taxes (IVA).
    Route::get('/taxes', [TaxController::class, 'config'])->name('taxes.config');

    // Etiquetas (labels) + label roll-up report.
    Route::get('/reports/by-label', [LabelController::class, 'report'])->name('reports.by-label');
    Route::get('/labels', [LabelController::class, 'index'])->name('labels.index');
    Route::post('/labels', [LabelController::class, 'store'])->name('labels.store');
    Route::put('/labels/{label}', [LabelController::class, 'update'])->name('labels.update');
    Route::delete('/labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');

    // Reports.
    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])->name('reports.income-statement');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('/reports/averages', [ReportController::class, 'averages'])->name('reports.averages');
    Route::get('/reports/iva', [TaxController::class, 'iva'])->name('reports.iva');

    // FEA — managed / adjusted cash flow.
    Route::get('/managed-cash-flow', [ManagedCashFlowController::class, 'show'])->name('managed-cash-flow.show');
    Route::post('/managed-cash-flow', [ManagedCashFlowController::class, 'store'])->name('managed-cash-flow.store');

    // Presupuesto (budget) — annual + monthly.
    Route::get('/budgets/monthly', [BudgetController::class, 'monthly'])->name('budgets.monthly');
    Route::post('/budgets/monthly', [BudgetController::class, 'storeMonthly'])->name('budgets.monthly.store');
    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');

    // Profile.
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Login devices (sessions).
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
});
