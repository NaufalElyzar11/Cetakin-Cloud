<?php

use App\Access\Http\Controllers\AccountController;
use App\Access\Http\Controllers\RegistrationController;
use App\Access\Http\Controllers\SessionController;
use App\Http\Middleware\PrivateResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Bootstrap'))->name('home');

Route::middleware(PrivateResponse::class)->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/register', [RegistrationController::class, 'create'])->name('register');
        Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/login', [SessionController::class, 'create'])->name('login');
        Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:30,1');
    });
    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
        Route::get('/account', [AccountController::class, 'index'])->name('account');
        Route::get('/account/customers/{customer}', [AccountController::class, 'show'])->name('account.customer');
    });
});
