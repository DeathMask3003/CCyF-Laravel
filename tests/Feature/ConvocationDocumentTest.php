<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\ConvocationDocuments;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        Schema::connection('legacy')->create('tm_areas', function (Blueprint $table): void {
            $table->unsignedInteger('area_id')->primary();
            $table->string('area_nom');
            $table->string('area_correo')->nullable();
            $table->string('direccion_plantel')->nullable();
            $table->string('espacio')->nullable();
            $table->unsignedInteger('matricula')->nullable();
            $table->decimal('monto', 11, 2)->nullable();
            $table->decimal('garantia', 11, 2)->nullable();
            $table->string('espacio_foto')->nullable();
            $table->unsignedInteger('matricula_foto')->nullable();
            $table->decimal('monto_foto', 11, 2)->nullable();
            $table->decimal('garantia_foto', 11, 2)->nullable();
            $table->integer('est');
        });
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
        DB::table('ccyf_planteles')->insert([['id' => 10, 'legacy_area_id' => 10, 'nombre' => 'Plantel Centro', 'nombre_clave' => 'centro', 'direccion' => 'Calle 10', 'activo' => 1],
            ['id' => 11, 'legacy_area_id' => 11, 'nombre' => 'Plantel Norte', 'nombre_clave' => 'norte', 'direccion' => 'Calle 11', 'activo' => 1]]);
        DB::connection('legacy')->table('tm_areas')->insert([
            ['area_id' => 10, 'area_nom' => 'Plantel Centro', 'direccion_plantel' => 'Calle 10', 'espacio' => 'Local 3', 'matricula' => 500, 'monto' => 2000, 'garantia' => 1000, 'est' => 1],
            ['area_id' => 11, 'area_nom' => 'Plantel Norte', 'direccion_plantel' => 'Calle 11', 'espacio' => 'Local 4', 'matricula' => 600, 'monto' => 2100, 'garantia' => 1100, 'est' => 1],
            ['area_id' => 12, 'area_nom' => 'CEMSaD Nuevo', 'direccion_plantel' => 'Calle 12', 'espacio' => 'Local 5', 'matricula' => 300, 'monto' => 1800, 'garantia' => 900, 'est' => 1],
            ['area_id' => 13, 'area_nom' => 'Departamento Interno', 'direccion_plantel' => null, 'espacio' => null, 'matricula' => null, 'monto' => null, 'garantia' => null, 'est' => 1],
            ['area_id' => 14, 'area_nom' => 'Plantel Inactivo', 'direccion_plantel' => null, 'espacio' => null, 'matricula' => null, 'monto' => null, 'garantia' => null, 'est' => 0],
        ]);
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
            ->assertSee('Plantel Norte')->assertSee('CEMSaD Nuevo')
            ->assertDontSee('Departamento Interno')->assertDontSee('Plantel Inactivo')
            ->assertSee('Todavía no has agregado planteles');
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
            'font_family' => 'dejavusans', 'font_size' => 9, 'firmante_nombre' => 'Mtra. Firmante Ejemplo']);
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

    public function test_preview_is_read_only_and_rejects_unknown_campuses(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $preview = $this->post('/emision-convocatorias/vista-previa', $this->payload())->assertOk();
        $this->assertStringStartsWith('%PDF', $preview->getContent());
        $this->assertDatabaseCount('ccyf_convocatoria_documentos', 0);
        $invalid = $this->payload();
        $invalid['planteles'][99] = $invalid['planteles'][10];
        unset($invalid['planteles'][10]);
        $this->post('/emision-convocatorias', $invalid)->assertSessionHasErrors('planteles');
        $this->assertDatabaseCount('ccyf_convocatoria_documentos', 0);
    }

    public function test_draft_preview_opens_with_pending_campus_data_without_relaxing_save(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $draft = $this->payload([
            'firmante_nombre' => null,
            'planteles' => [10 => ['espacio' => null, 'monto' => null, 'fecha_inicio' => null]],
        ]);
        $preview = $this->post('/emision-convocatorias/vista-previa', $draft)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $preview->getContent());
        $this->assertDatabaseCount('ccyf_convocatoria_documentos', 0);
        $this->post('/emision-convocatorias', $draft)->assertSessionHasErrors('firmante_nombre');
        $this->assertDatabaseCount('ccyf_convocatoria_documentos', 0);
    }

    public function test_unlinked_plantel_can_be_saved_from_legacy_catalog(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $payload = $this->payload();
        $payload['planteles'][12] = $payload['planteles'][10];
        unset($payload['planteles'][10]);
        $payload['planteles'][12]['direccion'] = 'Calle 12';
        $this->post('/emision-convocatorias', $payload)->assertRedirect();
        $localId = DB::table('ccyf_planteles')->where('legacy_area_id', 12)->value('id');
        $this->assertNotNull($localId);
        $this->assertDatabaseHas('ccyf_convocatoria_documento_planteles', [
            'plantel_id' => $localId, 'nombre' => 'CEMSaD Nuevo', 'direccion' => 'Calle 12',
        ]);
        $document = DB::table('ccyf_convocatoria_documentos')->value('id');
        $this->get('/emision-convocatorias/'.$document.'/editar')->assertOk()
            ->assertSee('value="Calle 12"', false);
    }

    public function test_photocopy_uses_its_own_values_from_legacy_area(): void
    {
        DB::table('ccyf_tipos_servicio')->insert(['id' => 2, 'nombre' => 'Fotocopiado',
            'nombre_clave' => 'fotocopiado', 'descripcion' => 'Servicio', 'plantilla' => 'fotocopiado', 'activo' => 1]);
        DB::table('ccyf_convocatorias')->insert(['id' => 9, 'numero' => 'Cuarta-2026-Fotocopiado',
            'numero_clave' => 'cuarta-2026-fotocopiado', 'servicio_id' => 2, 'activo' => 1]);
        DB::connection('legacy')->table('tm_areas')->where('area_id', 10)->update([
            'espacio_foto' => 'Módulo 2', 'matricula_foto' => 750,
            'monto_foto' => 3200, 'garantia_foto' => 1600,
        ]);
        $campus = app(ConvocationDocuments::class)->campuses(9, true)->firstWhere('id', 10);
        $this->assertSame('Módulo 2', $campus->espacio);
        $this->assertSame(750, $campus->matricula);
        $this->assertEquals(3200, $campus->monto);
        $this->assertEquals(1600, $campus->garantia);
        DB::table('ccyf_plantel_servicios')->insert([
            'plantel_id' => 10, 'servicio_id' => 2, 'espacio' => 'Local configurado',
            'matricula' => 740, 'monto' => 3100, 'garantia' => 1500,
        ]);
        DB::connection('legacy')->table('tm_areas')->where('area_id', 10)
            ->update(['monto_foto' => null]);
        $configured = app(ConvocationDocuments::class)->campuses(9, true)->firstWhere('id', 10);
        $this->assertSame('Módulo 2', $configured->espacio);
        $this->assertEquals(3100, $configured->monto);
    }

    public function test_html_sanitizer_keeps_format_without_active_content(): void
    {
        $clean = app(ConvocationDocuments::class)->cleanHtml('<p onclick="evil()" style="text-align:center;color:red">Bases <strong>claras</strong><script>alert(1)</script><a href="javascript:alert(1)">enlace</a></p><div align="justify">Texto justificado</div>');
        $this->assertStringContainsString('<strong>claras</strong>', $clean);
        $this->assertStringContainsString('style="text-align:center"', $clean);
        $this->assertStringContainsString('style="text-align:justify"', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('color:red', $clean);
        $rich = app(ConvocationDocuments::class)->cleanHtml('<p style="text-align:justify">Texto <span style="font-family:dejavuserif;font-size:11pt;color:red" onclick="evil()">destacado</span></p><table onclick="evil()"><tbody><tr><th>Concepto</th><th>Detalle</th></tr><tr><td>Uno</td><td>Dos</td></tr></tbody></table>');
        $this->assertStringContainsString('font-family:dejavuserif;font-size:11pt', $rich);
        $this->assertStringContainsString('<table><tbody><tr><th>Concepto</th><th>Detalle</th>', $rich);
        $this->assertStringNotContainsString('onclick', $rich);
        $this->assertStringNotContainsString('color:red', $rich);
        $this->assertStringNotContainsString('font-family:arial', app(ConvocationDocuments::class)->cleanHtml('<span style="font-family:arial;font-size:80pt">No permitido</span>'));
    }

    public function test_editor_uploads_a_private_image_and_includes_it_in_preview_and_saved_pdf(): void
    {
        Storage::fake('local');
        $this->be(LegacyUser::findOrFail(1));
        $this->post('/emision-convocatorias/imagenes', ['image' => UploadedFile::fake()->image('qr.png', 240, 240)])
            ->assertForbidden();
        $this->be(LegacyUser::findOrFail(2));
        $this->post('/emision-convocatorias/imagenes', ['image' => UploadedFile::fake()->create('vector.svg', 5, 'image/svg+xml')])
            ->assertSessionHasErrors('image');
        $upload = $this->postJson('/emision-convocatorias/imagenes', [
            'image' => UploadedFile::fake()->image('qr.png', 240, 240),
        ])->assertCreated()->assertJsonStructure(['url', 'width']);
        $url = $upload->json('url');
        $this->assertSame(180, $upload->json('width'));
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->be(LegacyUser::findOrFail(1));
        $this->get($url)->assertForbidden();
        $this->be(LegacyUser::findOrFail(2));

        $details = '<h2>Bases</h2><p>Consulta el código QR para registrar tu propuesta.</p>'
            .'<p style="text-align:justify"><img src="'.$url.'" width="170" alt="QR de registro" onerror="evil()"></p>';
        $clean = app(ConvocationDocuments::class)->cleanHtml($details);
        $this->assertStringContainsString('width="170"', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $resized = app(ConvocationDocuments::class)->cleanHtml('<p style="text-align:center"><img src="'.$url.'" style="width:220px;height:auto" alt="QR"></p>');
        $this->assertStringContainsString('width="220"', $resized);
        $this->assertStringNotContainsString('http://evil', app(ConvocationDocuments::class)->cleanHtml('<img src="http://evil.test/qr.png">'));

        $preview = $this->post('/emision-convocatorias/vista-previa', $this->payload([
            'detalles_html' => $details,
        ]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('/Subtype /Image', $preview->getContent());
        $this->post('/emision-convocatorias', $this->payload(['detalles_html' => $details]))->assertRedirect();
        $this->assertStringContainsString($url, DB::table('ccyf_convocatoria_documentos')->value('detalles_html'));
        $id = DB::table('ccyf_convocatoria_documentos')->value('id');
        $this->assertStringContainsString('/Subtype /Image', $this->get('/emision-convocatorias/'.$id.'/pdf')->getContent());

        $templateBody = $details.'<p>'.str_repeat('Bases para la convocatoria de cafetería. ', 4).'</p>';
        $this->post('/emision-convocatorias/plantillas/cafeteria', [
            'cuerpo_html' => $templateBody, 'font_family' => 'dejavusans', 'font_size' => 9,
        ])->assertRedirect();
        $this->assertStringContainsString($url, DB::table('ccyf_convocatoria_plantillas')->value('cuerpo_html'));
        $this->get('/emision-convocatorias/nueva?convocatoria=8')->assertOk()->assertSee($url)
            ->assertSee('vendor/tinymce/tinymce.min.js', false)
            ->assertSee('data-tiny-base=', false);
        $this->get('/emision-convocatorias/plantillas/cafeteria')->assertOk()
            ->assertSee('vendor/tinymce/tinymce.min.js', false);
    }

    public function test_service_template_is_editable_and_new_documents_use_its_style(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $this->get('/emision-convocatorias/plantillas/cafeteria')->assertOk()
            ->assertSee('Entrega de la Propuesta')->assertSee('DejaVu Sans')
            ->assertSee('vendor/tinymce/tinymce.min.js', false)
            ->assertSee('data-tiny-base=', false);
        $body = '<h2 style="text-align:center">Convocatoria actualizada</h2><p style="text-align:justify">'.str_repeat('Bases editables para cafetería. ', 5).'</p>';
        $this->post('/emision-convocatorias/plantillas/cafeteria', [
            'cuerpo_html' => $body, 'font_family' => 'dejavuserif', 'font_size' => '9.5',
        ])->assertRedirect();
        $this->assertDatabaseHas('ccyf_convocatoria_plantillas', ['servicio_id' => 1,
            'font_family' => 'dejavuserif', 'font_size' => 9.5]);
        $this->assertStringContainsString('text-align:center', DB::table('ccyf_convocatoria_plantillas')->value('cuerpo_html'));
        $this->get('/emision-convocatorias/nueva?convocatoria=8')->assertOk()
            ->assertSee('Convocatoria actualizada')->assertSee('value="9.5"', false)
            ->assertSee('vendor/tinymce/tinymce.min.js', false);
        $this->post('/emision-convocatorias', $this->payload([
            'detalles_html' => $body, 'font_family' => 'dejavuserif', 'font_size' => '9.5',
        ]))->assertRedirect();
        $this->assertDatabaseHas('ccyf_convocatoria_documentos', ['font_family' => 'dejavuserif', 'font_size' => 9.5]);
        $this->assertStringContainsString('text-align:justify', DB::table('ccyf_convocatoria_documentos')->value('detalles_html'));
        $this->post('/emision-convocatorias/vista-previa', $this->payload([
            'detalles_html' => $body, 'font_family' => 'dejavuserif', 'font_size' => '9.5',
        ]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_full_service_template_can_be_rendered_to_pdf(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        Storage::fake('local');
        $body = app(ConvocationDocuments::class)->defaultTemplate('Cafetería');
        $this->assertStringContainsString('Entrega de la Propuesta', $body);
        $upload = $this->postJson('/emision-convocatorias/imagenes', [
            'image' => UploadedFile::fake()->image('qr.png', 240, 240),
        ])->assertCreated();
        $qr = '<p style="text-align:center"><img src="'.$upload->json('url').'" width="180" alt="QR"></p>';
        $body = substr_replace($body, $qr, strrpos($body, '</td>'), 0);
        $preview = $this->post('/emision-convocatorias/vista-previa', $this->payload([
            'detalles_html' => $body,
        ]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $preview->getContent());
        $this->assertStringContainsString('/Subtype /Image', $preview->getContent());
    }

    public function test_annex_precedes_requirements_and_signature_stays_at_end(): void
    {
        $documents = app(ConvocationDocuments::class);
        foreach (['Cafetería', 'Fotocopiado'] as $service) {
            $details = $documents->detailsWithAnnex($documents->defaultTemplate($service),
                '<h2>Anexo I · Planteles participantes</h2><table><tr><td>Plantel Centro</td></tr></table>');
            $this->assertLessThan(mb_strpos($details, 'R E Q U I S I T O S'), mb_strpos($details, 'Anexo I · Planteles participantes'));
            $this->assertLessThan(mb_strpos($details, 'ATENTAMENTE'), mb_strpos($details, 'Plantel Centro'));
            $this->assertStringContainsString('__________________________________', $details);
            $this->assertStringContainsString('ccyf-signature-table', $details);
            $this->assertStringContainsString('Mtra. Firmante Ejemplo', $documents->detailsWithAnnex(
                $documents->defaultTemplate($service), '<p>Anexo</p>', 'Mtra. Firmante Ejemplo'));
        }
    }

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'convocatoria_id' => 8, 'titulo' => 'Convocatoria de cafetería',
            'detalles_html' => '<h2>Bases</h2><p>Entregar propuesta.<script>alert(1)</script></p>',
            'font_family' => 'dejavusans', 'font_size' => '9', 'firmante_nombre' => 'Mtra. Firmante Ejemplo',
            'planteles' => [10 => ['seleccionado' => 1, 'direccion' => 'Calle 10', 'espacio' => 'Local 3',
                'matricula' => 500, 'monto' => '2400.00', 'garantia' => '1000.00', 'fecha_inicio' => '2026-10-01']],
        ], $override);
    }
}
