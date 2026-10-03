<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InstitutionalErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.debug', false);
    }

    public function test_missing_page_uses_institutional_design(): void
    {
        $this->get('/ruta-inexistente-ccyf-404')
            ->assertNotFound()
            ->assertSee('No encontramos esta página')
            ->assertSee('ccyf-default.svg')
            ->assertSee('css/errors.css');
    }

    public function test_common_http_errors_show_their_own_spanish_message(): void
    {
        Route::get('/probar-error-institucional/{status}', fn (int $status) => abort($status));

        foreach ([
            403 => 'No tienes acceso a esta sección',
            419 => 'Tu sesión necesita renovarse',
            429 => 'Espera un momento para continuar',
            500 => 'Tuvimos un problema al procesar tu solicitud',
            503 => 'Volveremos en un momento',
            505 => 'Esta conexión no es compatible',
        ] as $status => $message) {
            $this->get("/probar-error-institucional/{$status}")
                ->assertStatus($status)
                ->assertSee($message)
                ->assertSee('ccyf-default.svg');
        }
    }

    public function test_other_http_errors_use_institutional_fallback(): void
    {
        Route::get('/probar-error-institucional/{status}', fn (int $status) => abort($status));

        $this->get('/probar-error-institucional/418')
            ->assertStatus(418)
            ->assertSee('No pudimos abrir esta página');

        $this->get('/probar-error-institucional/501')
            ->assertStatus(501)
            ->assertSee('Tuvimos un problema temporal');
    }
}
