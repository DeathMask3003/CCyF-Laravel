<?php

use App\Http\Controllers\Auth\AccessController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\AcceptedProposalController;
use App\Http\Controllers\CampusController;
use App\Http\Controllers\ConvocationController;
use App\Http\Controllers\ConvocationDocumentController;
use App\Http\Controllers\ConvocationLinkController;
use App\Http\Controllers\CcyfRoleController;
use App\Http\Controllers\CcyfUserController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\DocumentUpdateController;
use App\Http\Controllers\FinishedExportController;
use App\Http\Controllers\NewOfficeController;
use App\Http\Controllers\OfficeReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PriceCatalogController;
use App\Http\Controllers\PermitTrackingController;
use App\Http\Controllers\PermitTrackingExportController;
use App\Http\Controllers\PrevaluationController;
use App\Http\Controllers\ServiceTypeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');
Route::get('/marca/logo', [BrandingController::class, 'logo'])->name('branding.logo');

Route::middleware('guest')->group(function (): void {
    Route::get('/acceso', [AccessController::class, 'create'])->name('login');
    Route::post('/acceso', [AccessController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/acceso/google', [GoogleController::class, 'redirect'])->middleware('throttle:5,1')->name('login.google');
    Route::get('/registro', [RegistrationController::class, 'create'])->name('register');
    Route::post('/registro', [RegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/recuperar-acceso', [PasswordRecoveryController::class, 'request'])->name('password.request');
    Route::post('/recuperar-acceso', [PasswordRecoveryController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-acceso/{token}', [PasswordRecoveryController::class, 'edit'])->name('password.reset');
    Route::post('/restablecer-acceso', [PasswordRecoveryController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    Route::redirect('/verificacion', '/acceso');
});

Route::get('/acceso/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:10,1')->name('login.google.callback');

Route::middleware(['auth', 'ccyf.active'])->group(function (): void {
    Route::get('/administracion/identidad', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::put('/administracion/identidad', [BrandingController::class, 'update'])
        ->middleware('throttle:10,1')->name('branding.update');
    Route::get('/panel', fn () => view('dashboard'))->name('dashboard');
    Route::post('/salir', [AccessController::class, 'destroy'])->name('logout');
    Route::get('/mi-perfil', [ProfileController::class, 'show'])->name('perfil.show');
    Route::get('/mi-perfil/google/vincular', [GoogleController::class, 'link'])->middleware('throttle:5,1')->name('perfil.google.link');
    Route::get('/mi-perfil/foto', [ProfileController::class, 'photo'])->name('perfil.photo');
    Route::post('/mi-perfil/foto', [ProfileController::class, 'uploadPhoto'])->middleware('throttle:5,1')->name('perfil.photo-upload');
    Route::get('/mi-perfil/firma', [ProfileController::class, 'signature'])->name('perfil.signature');
    Route::post('/mi-perfil/firma', [ProfileController::class, 'uploadSignature'])->middleware('throttle:5,1')->name('perfil.signature-upload');
    Route::post('/mi-perfil/efirma', [ProfileController::class, 'uploadSatSignature'])->middleware('throttle:5,1')->name('perfil.sat-upload');
    Route::delete('/mi-perfil/efirma', [ProfileController::class, 'deleteSatSignature'])->middleware('throttle:5,1')->name('perfil.sat-delete');
    Route::put('/mi-perfil/firma/metodo', [ProfileController::class, 'signatureMethod'])->name('perfil.signature-method');
    Route::put('/mi-perfil', [ProfileController::class, 'update'])->name('perfil.update');
    Route::put('/mi-perfil/contrasena', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('perfil.password');
    Route::get('/ubicaciones', [LocationController::class, 'index'])->name('ubicaciones.index');

    Route::get('/observaciones-quejas', [ComplaintController::class, 'index'])->name('quejas.index');
    Route::get('/observaciones-quejas/manual/nueva', [ComplaintController::class, 'createManual'])->name('quejas.manual.create');
    Route::post('/observaciones-quejas/manual', [ComplaintController::class, 'storeManual'])->name('quejas.manual.store');
    Route::get('/observaciones-quejas/manual/{complaint}/editar', [ComplaintController::class, 'editManual'])->whereNumber('complaint')->name('quejas.manual.edit');
    Route::put('/observaciones-quejas/manual/{complaint}', [ComplaintController::class, 'updateManual'])->whereNumber('complaint')->name('quejas.manual.update');
    Route::patch('/observaciones-quejas/manual/{complaint}/estado', [ComplaintController::class, 'toggleManual'])->whereNumber('complaint')->name('quejas.manual.toggle');
    Route::get('/observaciones-quejas/evidencias/{origin}/{id}', [ComplaintController::class, 'evidence'])->whereIn('origin', ['historico', 'local'])->whereNumber('id')->name('quejas.evidence');
    Route::get('/observaciones-quejas/participaciones/{origin}/{record}', [ComplaintController::class, 'show'])->whereIn('origin', ['historico', 'actual'])->whereNumber('record')->name('quejas.show');
    Route::post('/observaciones-quejas/participaciones/{origin}/{record}', [ComplaintController::class, 'store'])->whereIn('origin', ['historico', 'actual'])->whereNumber('record')->name('quejas.store');

    Route::get('/contratos-permisionarios', [AcceptedProposalController::class, 'index'])->name('contratos.index');
    Route::post('/contratos-permisionarios/enviar-seleccionados', [AcceptedProposalController::class, 'sendBulk'])
        ->middleware('throttle:3,1')->name('contratos.send-bulk');
    Route::get('/contratos-permisionarios/plantilla/{service}', [AcceptedProposalController::class, 'template'])
        ->name('contratos.template');
    Route::post('/contratos-permisionarios/plantilla/{service}', [AcceptedProposalController::class, 'saveTemplate'])
        ->name('contratos.template.save');
    Route::get('/contratos-permisionarios/{key}', [AcceptedProposalController::class, 'show'])->name('contratos.show');
    Route::put('/contratos-permisionarios/{key}/datos', [AcceptedProposalController::class, 'saveTerms'])
        ->name('contratos.terms');
    Route::get('/contratos-permisionarios/{key}/borrador', [AcceptedProposalController::class, 'preview'])
        ->name('contratos.preview');
    Route::get('/contratos-permisionarios/{key}/enviado', [AcceptedProposalController::class, 'sentPdf'])
        ->name('contratos.sent-pdf');
    Route::post('/contratos-permisionarios/{key}/enviar', [AcceptedProposalController::class, 'send'])
        ->middleware('throttle:3,1')->name('contratos.send');
    Route::post('/ubicaciones', [LocationController::class, 'store'])->middleware('throttle:10,1')->name('ubicaciones.store');

    Route::get('/actualizacion-documentacion', [DocumentUpdateController::class, 'index'])->name('documentacion.index');
    Route::get('/actualizacion-documentacion/{key}', [DocumentUpdateController::class, 'show'])->name('documentacion.show');
    Route::post('/actualizacion-documentacion/{key}', [DocumentUpdateController::class, 'update'])
        ->middleware('throttle:10,1')->name('documentacion.update');
    Route::get('/actualizacion-documentacion/{key}/archivos/{field}', [DocumentUpdateController::class, 'file'])->name('documentacion.file');
    Route::get('/actualizacion-documentacion/{key}/original/{field}', [DocumentUpdateController::class, 'original'])->name('documentacion.original');
    Route::get('/actualizacion-documentacion/{key}/versiones/{version}', [DocumentUpdateController::class, 'version'])
        ->whereNumber('version')->name('documentacion.version');

    Route::get('/nuevo-oficio', [NewOfficeController::class, 'index'])->name('oficios.index');
    Route::get('/nuevo-oficio/{category}', [NewOfficeController::class, 'show'])->whereNumber('category')->name('oficios.show');
    Route::post('/nuevo-oficio/{category}/precios', [NewOfficeController::class, 'save'])->whereNumber('category')->name('oficios.save');
    Route::post('/nuevo-oficio/{category}/registrar', [NewOfficeController::class, 'submit'])->whereNumber('category')->name('oficios.submit');
    Route::get('/nuevo-oficio/registros/{record}', [NewOfficeController::class, 'receipt'])->whereNumber('record')->name('oficios.receipt');

    Route::get('/convocatorias-pendientes', [OfficeReviewController::class, 'pending'])->name('revision.pending');
    Route::get('/emision-convocatorias', [ConvocationDocumentController::class, 'index'])->name('emision.index');
    Route::get('/emision-convocatorias/nueva', [ConvocationDocumentController::class, 'create'])->name('emision.create');
    Route::post('/emision-convocatorias/imagenes', [ConvocationDocumentController::class, 'uploadImage'])
        ->middleware('throttle:20,1')->name('emision.images.upload');
    Route::get('/emision-convocatorias/imagenes/{image}', [ConvocationDocumentController::class, 'image'])
        ->where('image', '[0-9a-f-]{36}\\.(?:png|jpg)')->name('emision.image');
    Route::get('/emision-convocatorias/plantillas/{service}', [ConvocationDocumentController::class, 'template'])
        ->whereIn('service', ['cafeteria', 'fotocopiado'])->name('emision.template');
    Route::post('/emision-convocatorias/plantillas/{service}', [ConvocationDocumentController::class, 'saveTemplate'])
        ->whereIn('service', ['cafeteria', 'fotocopiado'])->name('emision.template.save');
    Route::match(['post', 'put'], '/emision-convocatorias/vista-previa', [ConvocationDocumentController::class, 'previewDraft'])->name('emision.preview-draft');
    Route::post('/emision-convocatorias', [ConvocationDocumentController::class, 'store'])->name('emision.store');
    Route::get('/emision-convocatorias/{document}/editar', [ConvocationDocumentController::class, 'edit'])->whereNumber('document')->name('emision.edit');
    Route::put('/emision-convocatorias/{document}', [ConvocationDocumentController::class, 'update'])->whereNumber('document')->name('emision.update');
    Route::get('/emision-convocatorias/{document}/pdf', [ConvocationDocumentController::class, 'pdf'])->whereNumber('document')->name('emision.pdf');
    Route::post('/convocatorias-pendientes/finalizar-masivo', [OfficeReviewController::class, 'finishBulk'])->name('revision.finish-bulk');
    Route::post('/convocatorias-pendientes/no-aceptadas', [OfficeReviewController::class, 'rejectBulk'])->name('revision.reject-bulk');
    Route::get('/convocatorias-finalizadas', [OfficeReviewController::class, 'finished'])->name('revision.finished');
    Route::get('/convocatorias-finalizadas/exportar/{format}', [FinishedExportController::class, 'download'])
        ->whereIn('format', ['pdf', 'xlsx'])->name('revision.export');
    Route::get('/expedientes/{record}', [OfficeReviewController::class, 'show'])->whereNumber('record')->name('revision.show');
    Route::get('/expedientes/{record}/evaluacion-final.pdf', [OfficeReviewController::class, 'localFinalEvaluationPdf'])
        ->whereNumber('record')->name('revision.final-evaluation-pdf');
    Route::get('/expedientes/{record}/carta-resultado.pdf', [OfficeReviewController::class, 'localResultLetterPdf'])
        ->whereNumber('record')->name('revision.result-letter-pdf');
    Route::post('/expedientes/{record}/finalizar', [OfficeReviewController::class, 'finish'])->whereNumber('record')->name('revision.finish');
    Route::post('/expedientes/{record}/notificar', [OfficeReviewController::class, 'notify'])->whereNumber('record')->name('revision.notify');
    Route::get('/expedientes/{record}/archivos/{file}', [OfficeReviewController::class, 'file'])->whereNumber(['record', 'file'])->name('revision.file');
    Route::get('/convocatorias-finalizadas/historico/{record}', [OfficeReviewController::class, 'historical'])->whereNumber('record')->name('revision.historical');
    Route::get('/convocatorias-finalizadas/historico/{record}/evaluacion-final.pdf', [OfficeReviewController::class, 'historicalFinalEvaluationPdf'])
        ->whereNumber('record')->name('revision.historical-final-evaluation-pdf');
    Route::get('/convocatorias-finalizadas/historico/{record}/resultado-pdf', [OfficeReviewController::class, 'historicalResultPdf'])
        ->whereNumber('record')->name('revision.historical-result-pdf');
    Route::get('/convocatorias-finalizadas/historico/{record}/archivo/{key}', [OfficeReviewController::class, 'historicalFile'])->whereNumber('record')->name('revision.historical-file');

    Route::get('/seguimiento-permisionarios', [PermitTrackingController::class, 'index'])->name('seguimiento.index');
    Route::put('/seguimiento-permisionarios/{key}', [PermitTrackingController::class, 'save'])->name('seguimiento.save');
    Route::put('/seguimiento-permisionarios/{key}/renovar', [PermitTrackingController::class, 'renew'])->name('seguimiento.renew');
    Route::get('/seguimiento-permisionarios/{key}/archivos/{number}', [PermitTrackingController::class, 'file'])->whereNumber('number')->name('seguimiento.file');
    Route::get('/seguimiento-permisionarios/{key}/expediente', [PermitTrackingController::class, 'zip'])->name('seguimiento.zip');
    Route::get('/seguimiento-permisionarios/exportar/{format}', [PermitTrackingExportController::class, 'download'])->whereIn('format', ['pdf', 'xlsx', 'csv'])->name('seguimiento.export');

    Route::get('/prevaluaciones', [PrevaluationController::class, 'index'])->name('prevaluaciones.index');
    Route::get('/prevaluaciones/anexo-global.pdf', [PrevaluationController::class, 'annex'])->name('prevaluaciones.annex');
    Route::get('/prevaluaciones/asignaciones', [PrevaluationController::class, 'assignments'])->name('prevaluaciones.assignments');
    Route::post('/prevaluaciones/asignaciones', [PrevaluationController::class, 'assign'])->name('prevaluaciones.assign');
    Route::post('/prevaluaciones/asignaciones/todas', [PrevaluationController::class, 'assignBulk'])->name('prevaluaciones.assign-bulk');
    Route::post('/prevaluaciones/{key}/tomar', [PrevaluationController::class, 'claim'])->name('prevaluaciones.claim');
    Route::delete('/prevaluaciones/{key}/tomar', [PrevaluationController::class, 'release'])->name('prevaluaciones.release');
    Route::put('/prevaluaciones/{key}', [PrevaluationController::class, 'save'])->name('prevaluaciones.save');
    Route::put('/prevaluaciones/{key}/observaciones', [PrevaluationController::class, 'note'])->name('prevaluaciones.note');
    Route::get('/prevaluaciones/{key}/documentos/{field}', [PrevaluationController::class, 'file'])->name('prevaluaciones.file');
    Route::post('/prevaluaciones/{key}/documentos/{field}/visto', [PrevaluationController::class, 'viewed'])->name('prevaluaciones.viewed');
    Route::get('/prevaluaciones/{key}/reporte.pdf', [PrevaluationController::class, 'report'])->name('prevaluaciones.report');

    Route::get('/catalogos', [PriceCatalogController::class, 'index'])->name('catalogos.index');
    Route::get('/catalogos/{category}', [PriceCatalogController::class, 'show'])->whereNumber('category')->name('catalogos.show');
    Route::post('/catalogos/{category}', [PriceCatalogController::class, 'prepare'])->whereNumber('category')->name('catalogos.prepare');
    Route::post('/catalogos/{category}/productos', [PriceCatalogController::class, 'storeProduct'])->whereNumber('category')->name('catalogos.products.store');
    Route::put('/catalogos/{category}/productos', [PriceCatalogController::class, 'updateProducts'])->whereNumber('category')->name('catalogos.products.update-bulk');
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
