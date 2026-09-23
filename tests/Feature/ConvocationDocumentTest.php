<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\ConvocationDocuments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConvocationDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite'); DB::purge('legacy');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);
        DB::table('ccyf_roles')->insert([['rol_id' => 1, 'rol_nom' => 'Sin acceso', 'est' => 1],
            ['rol_id' => 2, 'rol_nom' => 'Editor', 'est' => 1]]);
        foreach ([1, 2] as $id) {
            DB::table('ccyf_usuarios')->insert(['usu_id' => $id, 'rol_id' => $id,
                'usu_area' => 'Persona '.$id, 'usu_correo' => $id.'@example.org',
                'usu_pass' => Hash::make('secret'), 'est' => 1]);
        }
        DB::table('ccyf_role_permissions')->insert(['rol_id' => 2, 'menu_key' => 'convocatorias', 'allowed' => 1]);
        DB::table('ccyf_tipos_servicio')->insert(['id' => 1, 'nombre' => 'Cafetería',
            'nombre_clave' => 'cafeteria', 'descripcion' => 'Servicio', 'activo' => 1]);
        DB::table('ccyf_convocatorias')->insert(['id' => 8, 'numero' => 'Cuarta-2026-Cafetería',
            'numero_clave' => 'cuarta-2026-cafeteria', 'servicio_id' => 1, 'activo' => 1]);
        DB::table('ccyf_planteles')->insert([['id' => 10, 'nombre' => 'Plantel Centro', 'nombre_clave' => 'centro', 'direccion' => 'Calle 10', 'activo' => 1],
            ['id' => 11, 'nombre' => 'Plantel Norte', 'nombre_clave' => 'norte', 'direccion' => 'Calle 11', 'activo' => 1]]);
        DB::table('ccyf_convocatoria_planteles')->insert(['convocatoria_id' => 8, 'plantel_id' => 10]);
        DB::table('ccyf_plantel_servicios')->insert(['plantel_id' => 10, 'servicio_id' => 1,
            'espacio' => 'Local 3', 'matricula' => 500, 'monto' => 2000, 'garantia' => 1000]);
    }

    public function test_only_authorized_users_can_prepare_documents(): void
    {
        $this->be(LegacyUser::findOrFail(1));
        $this->get('/emision-convocatorias')->assertForbidden();
        $this->post('/emision-convocatorias', $this->payload())->assertForbidden();
        $this->be(LegacyUser::findOrFail(2));
        $this->get('/emision-convocatorias/nueva?convocatoria=8')->assertOk()->assertSee('Plantel Centro')
            ->assertDontSee('Plantel Norte')->assertSee('Todavía no has agregado planteles');
    }

    public function test_save_edit_and_pdf_use_campus_snapshot_without_touching_legacy(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $this->post('/emision-convocatorias', $this->payload())->assertRedirect();
        $id = DB::table('ccyf_convocatoria_documentos')->value('id');
        $this->assertDatabaseHas('ccyf_convocatoria_documento_planteles', [
            'documento_id' => $id, 'plantel_id' => 10, 'monto' => 2400,
        ]);
        $this->assertDatabaseHas('ccyf_convocatoria_documentos', ['id' => $id,
            'font_family' => 'dejavusans', 'font_size' => 9]);
        $this->assertDatabaseHas('ccyf_plantel_servicios', ['plantel_id' => 10, 'monto' => 2000]);
        $this->assertStringNotContainsString('<script', DB::table('ccyf_convocatoria_documentos')->value('detalles_html'));
        $this->get('/emision-convocatorias')->assertOk()->assertSee('Convocatoria de cafetería');
        $this->get('/emision-convocatorias/'.$id.'/editar')->assertOk()->assertSee('Plantel Centro');
        $this->put('/emision-convocatorias/vista-previa', $this->payload())->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $pdf = $this->get('/emision-convocatorias/'.$id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get('/emision-convocatorias/'.$id.'/pdf?descargar=1')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="convocatoria-'.$id.'.pdf"');
        $this->put('/emision-convocatorias/'.$id, $this->payload(['titulo' => 'Bases revisadas']))->assertRedirect();
        $this->assertDatabaseHas('ccyf_convocatoria_documentos', ['id' => $id, 'titulo' => 'Bases revisadas']);
        $this->assertDatabaseCount('ccyf_convocatoria_documento_planteles', 1);
    }

    public function test_preview_is_read_only_and_rejects_foreign_campuses(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $preview = $this->post('/emision-convocatorias/vista-previa', $this->payload())->assertOk();
        $this->assertStringStartsWith('%PDF', $preview->getContent());
        $this->assertDatabaseCount('ccyf_convocatoria_documentos', 0);
        $invalid = $this->payload();
        $invalid['planteles'][11] = $invalid['planteles'][10];
        unset($invalid['planteles'][10]);
        $this->post('/emision-convocatorias', $invalid)->assertSessionHasErrors('planteles');
        $this->assertDatabaseCount('ccyf_convocatoria_documentos', 0);
    }

    public function test_html_sanitizer_keeps_format_without_active_content(): void
    {
        $clean = app(ConvocationDocuments::class)->cleanHtml('<p onclick="evil()">Bases <strong>claras</strong><script>alert(1)</script><a href="javascript:alert(1)">enlace</a></p>');
        $this->assertStringContainsString('<strong>claras</strong>', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_service_template_is_editable_and_new_documents_use_its_style(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $this->get('/emision-convocatorias/plantillas/cafeteria')->assertOk()
            ->assertSee('Entrega de la Propuesta')->assertSee('DejaVu Sans');
        $body = '<h2>Convocatoria actualizada</h2><p>'.str_repeat('Bases editables para cafetería. ', 5).'</p>';
        $this->post('/emision-convocatorias/plantillas/cafeteria', [
            'cuerpo_html' => $body, 'font_family' => 'dejavuserif', 'font_size' => '9.5',
        ])->assertRedirect();
        $this->assertDatabaseHas('ccyf_convocatoria_plantillas', ['servicio_id' => 1,
            'font_family' => 'dejavuserif', 'font_size' => 9.5]);
        $this->get('/emision-convocatorias/nueva?convocatoria=8')->assertOk()
            ->assertSee('Convocatoria actualizada')->assertSee('value="9.5"', false);
        $this->post('/emision-convocatorias', $this->payload([
            'detalles_html' => $body, 'font_family' => 'dejavuserif', 'font_size' => '9.5',
        ]))->assertRedirect();
        $this->assertDatabaseHas('ccyf_convocatoria_documentos', ['font_family' => 'dejavuserif', 'font_size' => 9.5]);
    }

    public function test_full_service_template_can_be_rendered_to_pdf(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $body = app(ConvocationDocuments::class)->defaultTemplate('Cafetería');
        $this->assertStringContainsString('Entrega de la Propuesta', $body);
        $preview = $this->post('/emision-convocatorias/vista-previa', $this->payload([
            'detalles_html' => $body,
        ]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $preview->getContent());
    }

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'convocatoria_id' => 8, 'titulo' => 'Convocatoria de cafetería',
            'detalles_html' => '<h2>Bases</h2><p>Entregar propuesta.<script>alert(1)</script></p>',
            'font_family' => 'dejavusans', 'font_size' => '9',
            'planteles' => [10 => ['seleccionado' => 1, 'direccion' => 'Calle 10', 'espacio' => 'Local 3',
                'matricula' => 500, 'monto' => '2400.00', 'garantia' => '1000.00', 'fecha_inicio' => '2026-10-01']],
        ], $override);
    }
}
