<?php

use App\Access\Http\Controllers\AccountController;
use App\Access\Http\Controllers\RegistrationController;
use App\Access\Http\Controllers\SessionController;
use App\Access\Http\Controllers\StaffController;
use App\Http\Middleware\PrivateResponse;
use App\Intake\Http\RequestController;
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
    Route::middleware(['auth', 'auth.session'])->group(function (): void {
        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
        Route::get('/account', [AccountController::class, 'index'])->name('account');
        Route::get('/account/customers/{customer}', [AccountController::class, 'show'])->name('account.customer');
        Route::get('/staff', [StaffController::class, 'index'])->name('staff');
        Route::post('/staff/responsibilities', [StaffController::class, 'grant'])->middleware('throttle:20,1');
        Route::post('/staff/responsibilities/{grant}/revoke', [StaffController::class, 'revoke'])->middleware('throttle:20,1');
        Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/create', [RequestController::class, 'create'])->name('requests.create');
        Route::get('/requests/attempts/{operationId}', [RequestController::class, 'attempt'])->whereUuid('operationId')->name('requests.attempt');
        Route::post('/requests/attempts/{operationId}/reconcile', [RequestController::class, 'reconcile'])->whereUuid('operationId')->middleware('throttle:10,1')->name('requests.reconcile');
        Route::post('/requests', [RequestController::class, 'store'])->middleware('throttle:10,1')->name('requests.store');
        Route::get('/requests/{printRequest}', [RequestController::class, 'show'])->whereUuid('printRequest')->name('requests.show');
        Route::get('/requests/{printRequest}/files/{file}', [RequestController::class, 'download'])->whereUuid(['printRequest', 'file'])->middleware('throttle:30,1')->name('requests.file');
    });
});
