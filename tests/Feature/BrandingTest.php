<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
}
