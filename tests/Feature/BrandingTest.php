<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\Branding;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);

        DB::table('ccyf_roles')->insert([
            ['rol_id' => 1, 'rol_nom' => 'Concursante', 'est' => 1, 'legacy_rol_id' => 1],
            ['rol_id' => 18, 'rol_nom' => 'Administrador', 'est' => 1, 'legacy_rol_id' => 18],
        ]);
        DB::table('ccyf_role_permissions')->insert([
            'rol_id' => 18, 'menu_key' => 'Usuarios', 'allowed' => 1,
        ]);
        DB::table('ccyf_usuarios')->insert([
            ['usu_id' => 1, 'usu_area' => 'Administradora', 'usu_correo' => 'admin@example.test',
                'usu_pass' => bcrypt('clave-admin'), 'rol_id' => 18, 'est' => 1],
            ['usu_id' => 2, 'usu_area' => 'Participante', 'usu_correo' => 'participante@example.test',
                'usu_pass' => bcrypt('clave-participante'), 'rol_id' => 1, 'est' => 1],
        ]);
    }

    public function test_guest_sees_default_logo_above_login_inputs(): void
    {
        $this->get('/acceso')->assertOk()->assertSee('images/ccyf-default.svg')
            ->assertSee('Logo de Concurso de Cafetería y Fotocopiado');
    }

    public function test_existing_branding_row_can_be_read_before_document_migration(): void
    {
        DB::table('ccyf_branding')->insert([
            'id' => 1, 'title' => 'CCyF existente', 'motto' => 'Lema anterior',
            'logo_path' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::table('ccyf_branding', function (Blueprint $table): void {
            $table->dropColumn(['document_title', 'document_description', 'document_path']);
        });

        $current = app(Branding::class)->current();
        $this->assertSame('CCyF existente', $current->title);
        $this->assertSame('Información para participantes', $current->document_title);
        $this->assertNull($current->document_path);
        $this->actingAs(LegacyUser::findOrFail(1))->get('/panel')->assertOk();
    }

    public function test_only_administrator_can_change_branding(): void
    {
        $this->get('/administracion/identidad')->assertRedirect('/acceso');
        $this->actingAs(LegacyUser::findOrFail(2));
        $this->get('/administracion/identidad')->assertForbidden();
        $this->put('/administracion/identidad', [
            'title' => 'Otro título', 'motto' => 'Otro lema',
        ])->assertForbidden();
        $this->assertDatabaseCount('ccyf_branding', 0);
    }

    public function test_admin_updates_text_and_uploads_replaces_and_removes_logo(): void
    {
        Storage::fake('local');
        $this->actingAs(LegacyUser::findOrFail(1));
        $this->get('/administracion/identidad')->assertOk()->assertSee('Identidad del portal')
            ->assertSee('brand-logo-status')->assertSee('js/branding-preview.js');

        $this->put('/administracion/identidad', [
            'title' => 'Portal de servicios CCyF',
            'motto' => 'Participa con confianza.',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ])->assertRedirect()->assertSessionHas('status');

        $saved = DB::table('ccyf_branding')->where('id', 1)->first();
        $this->assertSame('Portal de servicios CCyF', $saved->title);
        Storage::disk('local')->assertExists($saved->logo_path);
        $this->get('/marca/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/panel')->assertOk()->assertSee('Portal de servicios CCyF');

        $this->put('/administracion/identidad', [
            'title' => 'Portal de servicios CCyF',
            'motto' => 'Participa con confianza.',
            'remove_logo' => '1',
        ])->assertRedirect();
        Storage::disk('local')->assertMissing($saved->logo_path);
        $this->assertNull(DB::table('ccyf_branding')->where('id', 1)->value('logo_path'));
        $this->get('/marca/logo')->assertNotFound();

        $this->post('/salir');
        $this->get('/acceso')->assertOk()->assertSee('Participa con confianza.')
            ->assertSee('images/ccyf-default.svg');
    }

    public function test_invalid_image_is_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs(LegacyUser::findOrFail(1));
        $this->put('/administracion/identidad', [
            'title' => 'Portal CCyF', 'motto' => 'Un lema nuevo',
            'logo' => UploadedFile::fake()->create('vector.svg', 4, 'image/svg+xml'),
        ])->assertSessionHasErrors(['logo' => 'Selecciona una imagen PNG, JPG o WebP válida.']);
        $this->put('/administracion/identidad', [
            'title' => 'Portal CCyF', 'motto' => 'Un lema nuevo',
            'logo' => UploadedFile::fake()->image('pequeno.png', 50, 50),
        ])->assertSessionHasErrors(['logo' => 'La imagen debe medir entre 80 × 80 y 4000 × 4000 píxeles.']);
        $this->assertDatabaseCount('ccyf_branding', 0);
    }

    public function test_admin_publishes_and_removes_home_pdf_for_contestants(): void
    {
        Storage::fake('local');
        $this->get('/marca/documento')->assertRedirect('/acceso');
        $this->actingAs(LegacyUser::findOrFail(2));
        $this->get('/panel')->assertOk()->assertDontSee('portal-document-card');
        $this->get('/administracion/identidad')->assertForbidden();
        $this->put('/administracion/identidad/documento', [
            'document_title' => 'Guía para concursantes',
        ])->assertForbidden();
        $this->get('/marca/documento')->assertNotFound();

        $this->actingAs(LegacyUser::findOrFail(1));
        $this->get('/administracion/identidad')->assertOk()
            ->assertSee('Documento destacado')->assertSee('Aún no hay PDF publicado');
        $this->put('/administracion/identidad/documento', [
            'document_title' => 'Guía para concursantes',
            'document_description' => 'Consulta los pasos para participar.',
            'document' => UploadedFile::fake()->createWithContent('guia.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"),
        ])->assertRedirect()->assertSessionHas('status');

        $first = DB::table('ccyf_branding')->where('id', 1)->first();
        $this->assertSame('Guía para concursantes', $first->document_title);
        Storage::disk('local')->assertExists($first->document_path);
        $this->get('/marca/documento')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/panel')->assertOk()->assertSee('portal-document-card');

        $this->actingAs(LegacyUser::findOrFail(2));
        $this->get('/panel')->assertOk()->assertSee('portal-document-card')
            ->assertSee('Guía para concursantes')->assertSee('Consulta los pasos para participar.')
            ->assertSee('revision-viewer.js')->assertSee('/marca/documento', false);
        $this->get('/marca/documento')->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs(LegacyUser::findOrFail(1));
        $this->put('/administracion/identidad/documento', [
            'document_title' => 'Documento actualizado',
            'document' => UploadedFile::fake()->createWithContent('nuevo.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"),
        ])->assertRedirect();
        Storage::disk('local')->assertMissing($first->document_path);
        $second = DB::table('ccyf_branding')->where('id', 1)->value('document_path');
        Storage::disk('local')->assertExists($second);

        $this->put('/administracion/identidad/documento', [
            'document_title' => 'Documento actualizado', 'remove_document' => '1',
        ])->assertRedirect();
        Storage::disk('local')->assertMissing($second);
        $this->assertNull(DB::table('ccyf_branding')->where('id', 1)->value('document_path'));
        $this->get('/marca/documento')->assertNotFound();
        $this->actingAs(LegacyUser::findOrFail(2));
        $this->get('/panel')->assertOk()->assertDontSee('portal-document-card');
    }

    public function test_home_document_rejects_non_pdf_and_does_not_change_published_file(): void
    {
        Storage::fake('local');
        $this->actingAs(LegacyUser::findOrFail(1));
        $this->put('/administracion/identidad/documento', [
            'document_title' => 'Archivo incorrecto',
            'document' => UploadedFile::fake()->createWithContent('falso.pdf', 'No es un PDF'),
        ])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('ccyf_branding', 0);
    }

    public function test_admin_publishes_and_hides_announcement_for_contestants_and_admins(): void
    {
        Storage::fake('local');
        $this->get('/marca/convocatoria/imagen')->assertRedirect('/acceso');
        $this->actingAs(LegacyUser::findOrFail(2));
        $this->get('/panel')->assertOk()->assertDontSee('portal-announcement-card');
        $this->put('/administracion/identidad/convocatoria', [
            'announcement_title' => 'Convocatoria abierta',
        ])->assertForbidden();

        $this->actingAs(LegacyUser::findOrFail(1));
        $this->put('/administracion/identidad/convocatoria', [
            'announcement_visible' => '1',
            'announcement_title' => 'Convocatoria de Cafetería y Fotocopiado',
            'announcement_description' => 'Consulta las bases y fechas de participación.',
            'announcement_image' => UploadedFile::fake()->image('convocatoria.jpg', 1200, 900),
            'announcement_link_1_label' => 'Consultar bases',
            'announcement_link_1_url' => 'https://cobaemex.edu.mx/convocatoria',
            'announcement_link_1_blank' => '1',
            'announcement_link_2_url' => 'https://cobaemex.edu.mx/requisitos',
        ])->assertRedirect()->assertSessionHas('status');

        $row = DB::table('ccyf_branding')->where('id', 1)->first();
        $this->assertSame(1, (int) $row->announcement_visible);
        Storage::disk('local')->assertExists($row->announcement_image_path);
        $this->get('/marca/convocatoria/imagen')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/panel')->assertOk()->assertSee('portal-announcement-card')
            ->assertSee('Convocatoria de Cafetería y Fotocopiado')
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('https://cobaemex.edu.mx/requisitos');
        $this->get('/administracion/identidad')->assertOk()->assertSee('Tarjeta de convocatoria');

        $this->actingAs(LegacyUser::findOrFail(2));
        $this->get('/panel')->assertOk()->assertSee('portal-announcement-card')
            ->assertSee('Consultar bases')->assertSee('Más información');
        $this->get('/marca/convocatoria/imagen')->assertOk();

        $this->actingAs(LegacyUser::findOrFail(1));
        $this->put('/administracion/identidad/convocatoria', [
            'announcement_title' => 'Convocatoria de Cafetería y Fotocopiado',
        ])->assertRedirect();
        $this->assertSame(0, (int) DB::table('ccyf_branding')->where('id', 1)->value('announcement_visible'));
        Storage::disk('local')->assertExists($row->announcement_image_path);
        $this->get('/panel')->assertOk()->assertDontSee('portal-announcement-card');
        $this->get('/marca/convocatoria/imagen')->assertOk();
        $this->actingAs(LegacyUser::findOrFail(2));
        $this->get('/panel')->assertOk()->assertDontSee('portal-announcement-card');
        $this->get('/marca/convocatoria/imagen')->assertNotFound();
    }

    public function test_announcement_rejects_invalid_links_and_files_without_replacing_existing_media(): void
    {
        Storage::fake('local');
        $this->actingAs(LegacyUser::findOrFail(1));
        $this->put('/administracion/identidad/convocatoria', [
            'announcement_title' => 'Convocatoria de prueba',
            'announcement_link_1_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('announcement_link_1_url');
        $this->put('/administracion/identidad/convocatoria', [
            'announcement_title' => 'Convocatoria de prueba',
            'announcement_image' => UploadedFile::fake()->create('archivo.svg', 5, 'image/svg+xml'),
        ])->assertSessionHasErrors('announcement_image');
        $this->assertDatabaseCount('ccyf_branding', 0);
    }

    public function test_announcement_image_and_video_can_be_replaced_and_removed(): void
    {
        Storage::fake('local');
        $this->actingAs(LegacyUser::findOrFail(1));
        $video = UploadedFile::fake()->createWithContent('convocatoria.mp4',
            "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2mp41");
        $this->put('/administracion/identidad/convocatoria', [
            'announcement_title' => 'Convocatoria con video',
            'announcement_visible' => '1',
            'announcement_image' => UploadedFile::fake()->image('portada.png', 800, 600),
            'announcement_video' => $video,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $first = DB::table('ccyf_branding')->where('id', 1)->first();
        Storage::disk('local')->assertExists($first->announcement_image_path);
        Storage::disk('local')->assertExists($first->announcement_video_path);
        $this->get('/marca/convocatoria/video')->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get('/panel')->assertOk()->assertSee('<video controls playsinline', false)
            ->assertSee('poster="http://localhost:8082/marca/convocatoria/imagen"', false);

        $this->put('/administracion/identidad/convocatoria', [
            'announcement_title' => 'Convocatoria con video',
            'announcement_visible' => '1',
            'announcement_image' => UploadedFile::fake()->image('nueva.jpg', 1000, 700),
            'remove_announcement_video' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first->announcement_image_path);
        Storage::disk('local')->assertMissing($first->announcement_video_path);
        $this->get('/marca/convocatoria/video')->assertNotFound();

        $this->put('/administracion/identidad/convocatoria', [
            'announcement_title' => 'Convocatoria con video',
            'remove_announcement_image' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(DB::table('ccyf_branding')->where('id', 1)->value('announcement_image_path'));
    }
}
