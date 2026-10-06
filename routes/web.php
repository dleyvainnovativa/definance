<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Firebase session exchange (CSRF-protected).
Route::post('/auth/session', [AuthController::class, 'session'])->name('auth.session');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

Route::view('/login', 'pages.login')->name('login');

Route::middleware('auth')->group(function () {
    Route::view('/', 'pages.dashboard')->name('dashboard');

    Route::view('/accounts', 'pages.accounts')->name('accounts');
    Route::view('/entries', 'pages.entries')->name('entries');
    Route::view('/import', 'pages.import')->name('import');

    Route::view('/trial-balance', 'pages.reports.trial-balance')->name('trial-balance');
    Route::view('/income-statement', 'pages.reports.income-statement')->name('income-statement');
    Route::view('/balance-sheet', 'pages.reports.balance-sheet')->name('balance-sheet');
    Route::view('/cash-flow', 'pages.reports.cash-flow')->name('cash-flow');
    Route::view('/managed-cash-flow', 'pages.managed-cash-flow')->name('managed-cash-flow');
    Route::view('/iva', 'pages.reports.iva')->name('iva');

    Route::view('/profile', 'pages.profile')->name('profile');
});
