<?php

use App\Http\Controllers\Auth\AccessController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');

Route::middleware('guest')->group(function (): void {
    Route::get('/acceso', [AccessController::class, 'create'])->name('login');
    Route::post('/acceso', [AccessController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/verificacion', [AccessController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/verificacion', [AccessController::class, 'verify'])->middleware('throttle:5,1')->name('mfa.verify');
    Route::post('/verificacion/reenviar', [AccessController::class, 'resend'])->middleware('throttle:2,1')->name('mfa.resend');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/panel', fn () => view('dashboard'))->name('dashboard');
    Route::post('/salir', [AccessController::class, 'destroy'])->name('logout');
});
