<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->delete('/logout', [AuthController::class, 'destroy'])
    ->name('logout');

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::put('/current-branch', [BranchController::class, 'update'])
        ->name('current-branch.update');
});
