<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\LegacyPasswordVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('auth.providers.users.model', LegacyUser::class);
        config()->set('ccyf.legacy_password_key', 'test-secret');
        DB::purge('sqlite');
        DB::purge('legacy');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);

        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->increments('usu_id');
            $table->string('usu_area');
            $table->string('usu_correo');
            $table->string('usu_pass');
            $table->integer('est');
        });

        DB::connection('legacy')->table('tm_usuario')->insert([
            'usu_id' => 7,
            'usu_area' => 'Prueba',
            'usu_correo' => 'prueba@example.test',
            'usu_pass' => password_hash('clave-prueba', PASSWORD_BCRYPT),
            'est' => 1,
        ]);
        DB::table('ccyf_usuarios')->insert([
            'usu_id' => 7,
            'legacy_usu_id' => 7,
            'usu_area' => 'Prueba',
            'usu_correo' => 'prueba@example.test',
            'usu_pass' => password_hash('clave-prueba', PASSWORD_BCRYPT),
            'rol_id' => 1,
            'est' => 1,
        ]);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/panel');
        $this->get('/panel')->assertRedirect('/acceso');
        $this->get('/acceso')->assertOk();
    }

    public function test_valid_password_signs_in_without_email_code(): void
    {
        $this->post('/acceso', [
            'email' => 'PRUEBA@example.test',
            'password' => 'clave-prueba',
        ])->assertRedirect('/panel')->assertSessionMissing('ccyf.pending_user');

        $this->assertAuthenticated();
    }

    public function test_invalid_password_does_not_authenticate(): void
    {
        $this->post('/acceso', [
            'email' => 'prueba@example.test',
            'password' => 'equivocada',
        ])->assertSessionHasErrors('email')->assertSessionMissing('ccyf.pending_user');

        $this->assertGuest();
    }

    public function test_old_verification_link_returns_to_login(): void
    {
        $this->get('/verificacion')->assertRedirect('/acceso');
        $this->assertGuest();
    }

    public function test_inactive_role_still_cannot_sign_in(): void
    {
        DB::table('ccyf_roles')->insert(['rol_id'=>1,'legacy_rol_id'=>1,'rol_nom'=>'Concursante','est'=>0]);
        $this->post('/acceso', ['email'=>'prueba@example.test','password'=>'clave-prueba'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_legacy_encrypted_password_is_supported(): void
    {
        $iv = str_repeat('a', 16);
        $ciphertext = openssl_encrypt('clave-vieja', 'aes-256-cbc', 'test-secret', OPENSSL_RAW_DATA, $iv);
        $this->assertIsString($ciphertext);

        $verifier = app(LegacyPasswordVerifier::class);
        $this->assertTrue($verifier->verify('clave-vieja', base64_encode($iv.$ciphertext)));
        $this->assertFalse($verifier->verify('incorrecta', base64_encode($iv.$ciphertext)));
    }
}
