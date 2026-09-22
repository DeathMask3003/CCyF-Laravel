<?php

use App\Http\Controllers\Auth\AccessController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\NewOfficeController;
use App\Http\Controllers\PriceCatalogController;
use App\Http\Controllers\ServiceTypeController;
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

    Route::get('/nuevo-oficio', [NewOfficeController::class, 'index'])->name('oficios.index');
    Route::get('/nuevo-oficio/{category}', [NewOfficeController::class, 'show'])->whereNumber('category')->name('oficios.show');
    Route::post('/nuevo-oficio/{category}/precios', [NewOfficeController::class, 'save'])->whereNumber('category')->name('oficios.save');

    Route::get('/catalogos', [PriceCatalogController::class, 'index'])->name('catalogos.index');
    Route::get('/catalogos/{category}', [PriceCatalogController::class, 'show'])->whereNumber('category')->name('catalogos.show');
    Route::post('/catalogos/{category}', [PriceCatalogController::class, 'prepare'])->whereNumber('category')->name('catalogos.prepare');
    Route::post('/catalogos/{category}/productos', [PriceCatalogController::class, 'storeProduct'])->whereNumber('category')->name('catalogos.products.store');
    Route::put('/catalogos/{category}/productos/{product}', [PriceCatalogController::class, 'updateProduct'])->whereNumber(['category', 'product'])->name('catalogos.products.update');

    Route::get('/tipos-documento', [DocumentTypeController::class, 'index'])->name('tipos.index');
    Route::post('/tipos-documento', [DocumentTypeController::class, 'store'])->name('tipos.store');
    Route::put('/tipos-documento/{type}', [DocumentTypeController::class, 'update'])->whereNumber('type')->name('tipos.update');
    Route::patch('/tipos-documento/{type}/estado', [DocumentTypeController::class, 'toggle'])->whereNumber('type')->name('tipos.toggle');

    Route::get('/tipos-servicios', [ServiceTypeController::class, 'index'])->name('servicios.index');
    Route::post('/tipos-servicios', [ServiceTypeController::class, 'store'])->name('servicios.store');
    Route::put('/tipos-servicios/{service}', [ServiceTypeController::class, 'update'])->whereNumber('service')->name('servicios.update');
    Route::patch('/tipos-servicios/{service}/estado', [ServiceTypeController::class, 'toggle'])->whereNumber('service')->name('servicios.toggle');
});
