<?php

use App\Http\Controllers\Auth\AccessController;
use App\Http\Controllers\CampusController;
use App\Http\Controllers\ConvocationController;
use App\Http\Controllers\ConvocationLinkController;
use App\Http\Controllers\CcyfRoleController;
use App\Http\Controllers\CcyfUserController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\FinishedExportController;
use App\Http\Controllers\NewOfficeController;
use App\Http\Controllers\OfficeReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PriceCatalogController;
use App\Http\Controllers\PermitTrackingController;
use App\Http\Controllers\PermitTrackingExportController;
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

Route::middleware(['auth', 'ccyf.active'])->group(function (): void {
    Route::get('/panel', fn () => view('dashboard'))->name('dashboard');
    Route::post('/salir', [AccessController::class, 'destroy'])->name('logout');
    Route::get('/mi-perfil', [ProfileController::class, 'show'])->name('perfil.show');
    Route::put('/mi-perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::put('/mi-perfil/contrasena', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('perfil.password');
    Route::get('/ubicaciones', [LocationController::class, 'index'])->name('ubicaciones.index');
    Route::post('/ubicaciones', [LocationController::class, 'store'])->middleware('throttle:10,1')->name('ubicaciones.store');

    Route::get('/nuevo-oficio', [NewOfficeController::class, 'index'])->name('oficios.index');
    Route::get('/nuevo-oficio/{category}', [NewOfficeController::class, 'show'])->whereNumber('category')->name('oficios.show');
    Route::post('/nuevo-oficio/{category}/precios', [NewOfficeController::class, 'save'])->whereNumber('category')->name('oficios.save');
    Route::post('/nuevo-oficio/{category}/registrar', [NewOfficeController::class, 'submit'])->whereNumber('category')->name('oficios.submit');
    Route::get('/nuevo-oficio/registros/{record}', [NewOfficeController::class, 'receipt'])->whereNumber('record')->name('oficios.receipt');

    Route::get('/convocatorias-pendientes', [OfficeReviewController::class, 'pending'])->name('revision.pending');
    Route::post('/convocatorias-pendientes/no-aceptadas', [OfficeReviewController::class, 'rejectBulk'])->name('revision.reject-bulk');
    Route::get('/convocatorias-finalizadas', [OfficeReviewController::class, 'finished'])->name('revision.finished');
    Route::get('/convocatorias-finalizadas/exportar/{format}', [FinishedExportController::class, 'download'])
        ->whereIn('format', ['pdf', 'xlsx'])->name('revision.export');
    Route::get('/expedientes/{record}', [OfficeReviewController::class, 'show'])->whereNumber('record')->name('revision.show');
    Route::post('/expedientes/{record}/finalizar', [OfficeReviewController::class, 'finish'])->whereNumber('record')->name('revision.finish');
    Route::get('/expedientes/{record}/archivos/{file}', [OfficeReviewController::class, 'file'])->whereNumber(['record', 'file'])->name('revision.file');
    Route::get('/convocatorias-finalizadas/historico/{record}', [OfficeReviewController::class, 'historical'])->whereNumber('record')->name('revision.historical');
    Route::get('/convocatorias-finalizadas/historico/{record}/archivo/{key}', [OfficeReviewController::class, 'historicalFile'])->whereNumber('record')->name('revision.historical-file');

    Route::get('/seguimiento-permisionarios', [PermitTrackingController::class, 'index'])->name('seguimiento.index');
    Route::put('/seguimiento-permisionarios/{key}', [PermitTrackingController::class, 'save'])->name('seguimiento.save');
    Route::put('/seguimiento-permisionarios/{key}/renovar', [PermitTrackingController::class, 'renew'])->name('seguimiento.renew');
    Route::get('/seguimiento-permisionarios/{key}/archivos/{number}', [PermitTrackingController::class, 'file'])->whereNumber('number')->name('seguimiento.file');
    Route::get('/seguimiento-permisionarios/{key}/expediente', [PermitTrackingController::class, 'zip'])->name('seguimiento.zip');
    Route::get('/seguimiento-permisionarios/exportar/{format}', [PermitTrackingExportController::class, 'download'])->whereIn('format', ['pdf', 'xlsx', 'csv'])->name('seguimiento.export');

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

    Route::get('/planteles', [CampusController::class, 'index'])->name('planteles.index');
    Route::get('/planteles/nuevo', [CampusController::class, 'create'])->name('planteles.create');
    Route::post('/planteles', [CampusController::class, 'store'])->name('planteles.store');
    Route::get('/planteles/{campus}/editar', [CampusController::class, 'edit'])->whereNumber('campus')->name('planteles.edit');
    Route::put('/planteles/{campus}', [CampusController::class, 'update'])->whereNumber('campus')->name('planteles.update');
    Route::patch('/planteles/{campus}/estado', [CampusController::class, 'toggle'])->whereNumber('campus')->name('planteles.toggle');

    Route::get('/convocatorias', [ConvocationController::class, 'index'])->name('convocatorias.index');
    Route::get('/convocatorias/nueva', [ConvocationController::class, 'create'])->name('convocatorias.create');
    Route::post('/convocatorias', [ConvocationController::class, 'store'])->name('convocatorias.store');
    Route::get('/convocatorias/{convocation}/editar', [ConvocationController::class, 'edit'])->whereNumber('convocation')->name('convocatorias.edit');
    Route::put('/convocatorias/{convocation}', [ConvocationController::class, 'update'])->whereNumber('convocation')->name('convocatorias.update');
    Route::patch('/convocatorias/{convocation}/estado', [ConvocationController::class, 'toggle'])->whereNumber('convocation')->name('convocatorias.toggle');

    Route::get('/enlaces-convocatoria', [ConvocationLinkController::class, 'index'])->name('enlaces.index');
    Route::get('/enlaces-convocatoria/{convocation}/editar', [ConvocationLinkController::class, 'edit'])->whereNumber('convocation')->name('enlaces.edit');
    Route::put('/enlaces-convocatoria/{convocation}', [ConvocationLinkController::class, 'update'])->whereNumber('convocation')->name('enlaces.update');

    Route::get('/usuarios', [CcyfUserController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/nuevo', [CcyfUserController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [CcyfUserController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/{user}/editar', [CcyfUserController::class, 'edit'])->whereNumber('user')->name('usuarios.edit');
    Route::put('/usuarios/{user}', [CcyfUserController::class, 'update'])->whereNumber('user')->name('usuarios.update');
    Route::patch('/usuarios/{user}/estado', [CcyfUserController::class, 'toggle'])->whereNumber('user')->name('usuarios.toggle');

    Route::get('/roles', [CcyfRoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/nuevo', [CcyfRoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [CcyfRoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/editar', [CcyfRoleController::class, 'edit'])->whereNumber('role')->name('roles.edit');
    Route::put('/roles/{role}', [CcyfRoleController::class, 'update'])->whereNumber('role')->name('roles.update');
    Route::patch('/roles/{role}/estado', [CcyfRoleController::class, 'toggle'])->whereNumber('role')->name('roles.toggle');
});
