<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Notifications\CcyfResetPassword;
use App\Services\LegacyPasswordVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\Mime\Email;
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

    public function test_roles_page_shows_assigned_user_counts_including_empty_roles(): void
    {
        DB::table('ccyf_roles')->insert([
            ['rol_id' => 1, 'rol_nom' => 'Administración', 'est' => true],
            ['rol_id' => 2, 'rol_nom' => 'Prevaluación', 'est' => true],
        ]);
        DB::table('ccyf_role_permissions')->insert([
            'rol_id' => 1, 'menu_key' => 'Rol', 'allowed' => true,
        ]);
        DB::table('ccyf_usuarios')->insert([
            'usu_area' => 'Segunda cuenta', 'usu_pass' => 'test', 'rol_id' => 1,
        ]);

        $this->actingAs(LegacyUser::findOrFail(7))->get('/roles')
            ->assertOk()
            ->assertSee('2 usuarios asignados')
            ->assertSee('0 usuarios asignados');
    }

    public function test_integrations_stay_off_locally_even_with_keys(): void
    {
        config()->set('app.env', 'local');
        config()->set('ccyf.turnstile.enabled', true);
        config()->set('ccyf.turnstile.site_key', 'site-test');
        config()->set('ccyf.turnstile.secret_key', 'secret-test');
        config()->set('ccyf.google_login_enabled', true);
        config()->set('services.google', ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'https://example.test/acceso/google/callback']);
        Http::fake();

        $this->get('/acceso')->assertOk()->assertDontSee('cf-turnstile')->assertDontSee('login/google');
        $this->get('/acceso/google')->assertNotFound();
        $this->post('/acceso', ['email' => 'prueba@example.test', 'password' => 'clave-prueba'])->assertRedirect('/panel');
        Http::assertNothingSent();
    }

    public function test_turnstile_fails_closed_in_production_and_checks_action_and_hostname(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.url', 'https://ccyf.example.test');
        config()->set('ccyf.turnstile.enabled', true);
        config()->set('ccyf.turnstile.site_key', 'site-test');
        config()->set('ccyf.turnstile.secret_key', 'secret-test');

        $this->get('/acceso')->assertSee('cf-turnstile');
        $this->post('/acceso', ['email' => 'prueba@example.test', 'password' => 'clave-prueba'])
            ->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();

        Http::fakeSequence()
            ->push(['success' => true, 'action' => 'register', 'hostname' => 'ccyf.example.test'])
            ->push(['success' => true, 'action' => 'login', 'hostname' => 'ccyf.example.test']);
        $this->post('/acceso', ['email' => 'prueba@example.test', 'password' => 'clave-prueba', 'cf-turnstile-response' => 'token'])
            ->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();

        $this->post('/acceso', ['email' => 'prueba@example.test', 'password' => 'clave-prueba', 'cf-turnstile-response' => 'token'])
            ->assertRedirect('/panel');
        $this->assertAuthenticated();
    }

    public function test_turnstile_can_be_tested_on_https_staging_but_stays_off_on_http(): void
    {
        config()->set('app.env', 'staging');
        config()->set('app.url', 'https://pruebas.cobaemex.edu.mx');
        config()->set('ccyf.turnstile.enabled', true);
        config()->set('ccyf.turnstile.site_key', 'staging-site');
        config()->set('ccyf.turnstile.secret_key', 'staging-secret');

        $this->get('/acceso')->assertSee('cf-turnstile');
        $this->post('/acceso', ['email' => 'prueba@example.test', 'password' => 'clave-prueba'])
            ->assertSessionHasErrors('cf-turnstile-response');

        Http::fake(['challenges.cloudflare.com/*' => Http::response([
            'success' => true,
            'action' => 'login',
            'hostname' => 'pruebas.cobaemex.edu.mx',
        ])]);
        $this->post('/acceso', [
            'email' => 'prueba@example.test',
            'password' => 'clave-prueba',
            'cf-turnstile-response' => 'staging-token',
        ])->assertRedirect('/panel');

        config()->set('app.url', 'http://pruebas.cobaemex.edu.mx');
        $this->get('/acceso')->assertDontSee('cf-turnstile');
    }

    public function test_google_sign_in_links_a_verified_gmail_account_by_stable_id(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.url', 'https://ccyf.example.test');
        config()->set('ccyf.google_login_enabled', true);
        config()->set('services.google', ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'https://ccyf.example.test/acceso/google/callback']);
        DB::table('ccyf_usuarios')->where('usu_id', 7)->update(['usu_correo' => 'prueba@gmail.com']);
        Socialite::fake('google', GoogleUser::fake(['id' => 'google-123', 'email' => 'prueba@gmail.com', 'email_verified' => true]));

        $this->get('/acceso')->assertSee('acceso/google');
        $this->get('/acceso/google')->assertRedirect('https://socialite.fake/google/authorize');
        $this->get('/acceso/google/callback')->assertRedirect('/panel');
        $this->assertSame('google-123', DB::table('ccyf_usuarios')->where('usu_id', 7)->value('google_sub'));
        $this->assertAuthenticatedAs(LegacyUser::findOrFail(7));
    }

    public function test_google_does_not_link_an_unverified_email(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.url', 'https://ccyf.example.test');
        config()->set('ccyf.google_login_enabled', true);
        config()->set('services.google', ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'https://ccyf.example.test/acceso/google/callback']);
        Socialite::fake('google', GoogleUser::fake(['id' => 'google-123', 'email' => 'prueba@example.test', 'email_verified' => false]));

        $this->get('/acceso/google')->assertRedirect();
        $this->get('/acceso/google/callback')->assertRedirect('/acceso')->assertSessionHasErrors('email');
        $this->assertNull(DB::table('ccyf_usuarios')->where('usu_id', 7)->value('google_sub'));
        $this->assertGuest();
    }

    public function test_google_can_be_enabled_on_https_staging_with_its_own_callback(): void
    {
        config()->set('app.env', 'staging');
        config()->set('app.url', 'https://pruebas.cobaemex.edu.mx');
        config()->set('ccyf.google_login_enabled', true);
        config()->set('services.google', [
            'client_id' => 'id',
            'client_secret' => 'secret',
            'redirect' => 'https://pruebas.cobaemex.edu.mx/acceso/google/callback',
        ]);
        DB::table('ccyf_usuarios')->where('usu_id', 7)->update(['usu_correo' => 'prueba@gmail.com']);
        Socialite::fake('google', GoogleUser::fake(['id' => 'google-staging', 'email' => 'prueba@gmail.com', 'email_verified' => true]));

        $this->get('/acceso')->assertSee('acceso/google');
        $this->get('/acceso/google')->assertRedirect('https://socialite.fake/google/authorize');
        $this->get('/acceso/google/callback')->assertRedirect('/panel');
        $this->assertAuthenticatedAs(LegacyUser::findOrFail(7));
    }

    public function test_google_stays_off_when_staging_callback_or_scheme_is_wrong(): void
    {
        config()->set('app.env', 'staging');
        config()->set('app.url', 'https://pruebas.cobaemex.edu.mx');
        config()->set('ccyf.google_login_enabled', true);
        config()->set('services.google', [
            'client_id' => 'id',
            'client_secret' => 'secret',
            'redirect' => 'https://ccyf.cobaemex.edu.mx/acceso/google/callback',
        ]);

        $this->get('/acceso')->assertDontSee('acceso/google');
        $this->get('/acceso/google')->assertNotFound();

        config()->set('app.url', 'http://pruebas.cobaemex.edu.mx');
        config()->set('services.google.redirect', 'http://pruebas.cobaemex.edu.mx/acceso/google/callback');
        $this->get('/acceso/google')->assertNotFound();
    }

    public function test_public_registration_requires_turnstile_only_in_production(): void
    {
        config()->set('app.env', 'production');
        config()->set('ccyf.turnstile.enabled', true);
        config()->set('ccyf.turnstile.site_key', 'site-test');
        config()->set('ccyf.turnstile.secret_key', 'secret-test');
        DB::table('ccyf_roles')->insert(['rol_id' => 12, 'legacy_rol_id' => 1, 'rol_nom' => 'Concursante', 'est' => 1]);

        $this->get('/registro')->assertSee('cf-turnstile');
        $this->post('/registro', [
            'nombre' => 'Persona de prueba', 'email' => 'nuevo@example.test', 'telefono' => '7222123456',
            'password' => 'ClaveSegura123', 'password_confirmation' => 'ClaveSegura123',
        ])->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseMissing('ccyf_usuarios', ['usu_correo' => 'nuevo@example.test']);
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

    public function test_remember_me_persists_a_token(): void
    {
        $this->post('/acceso', [
            'email' => 'prueba@example.test',
            'password' => 'clave-prueba',
            'remember' => '1',
        ])->assertRedirect('/panel');

        $this->assertNotEmpty(DB::table('ccyf_usuarios')->where('usu_id', 7)->value('remember_token'));
    }

    public function test_public_registration_creates_only_a_contestant(): void
    {
        DB::table('ccyf_roles')->insert(['rol_id' => 12, 'legacy_rol_id' => 1, 'rol_nom' => 'Concursante', 'est' => 1]);

        $this->post('/registro', [
            'nombre' => 'Juan López',
            'email' => 'NUEVO@example.test',
            'telefono' => '7222123456',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'rol_id' => 2,
        ])->assertRedirect('/panel');

        $user = DB::table('ccyf_usuarios')->where('usu_correo', 'nuevo@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame(12, $user->rol_id);
        $this->assertSame('5217222123456', $user->usu_telf);
        $this->assertTrue(Hash::check('ClaveSegura123', $user->usu_pass));
        $this->assertAuthenticatedAs(LegacyUser::findOrFail($user->usu_id));
    }

    public function test_duplicate_email_cannot_register_again(): void
    {
        DB::table('ccyf_roles')->insert(['rol_id' => 1, 'legacy_rol_id' => 1, 'rol_nom' => 'Concursante', 'est' => 1]);
        $this->post('/registro', [
            'nombre' => 'Otra Persona',
            'email' => 'PRUEBA@example.test',
            'telefono' => '7222123456',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
        ])->assertSessionHasErrors('email');
    }

    public function test_recovery_sends_a_link_and_changes_only_the_target_account(): void
    {
        Notification::fake();
        DB::table('ccyf_usuarios')->insert([
            'usu_id' => 8, 'usu_area' => 'Otra cuenta', 'usu_correo' => 'prueba@example.test',
            'usu_pass' => Hash::make('OtraClave123'), 'rol_id' => 1, 'est' => 1,
        ]);

        $this->post('/recuperar-acceso', ['email' => 'prueba@example.test'])
            ->assertSessionHas('status');
        Notification::assertSentTo(LegacyUser::findOrFail(7), CcyfResetPassword::class);
        Notification::assertSentTo(LegacyUser::findOrFail(8), CcyfResetPassword::class);
        $this->assertSame(2, DB::table('password_reset_tokens')->count());

        $token = Password::broker()->createToken(LegacyUser::findOrFail(7));
        $this->get(route('password.reset', ['token' => $token, 'cuenta' => 7]))->assertOk();
        $this->post('/restablecer-acceso', [
            'cuenta' => 7, 'token' => $token,
            'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123',
        ])->assertRedirect('/acceso');

        $this->assertTrue(Hash::check('NuevaClave123', LegacyUser::findOrFail(7)->usu_pass));
        $this->assertTrue(Hash::check('OtraClave123', LegacyUser::findOrFail(8)->usu_pass));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => '7']);
    }

    public function test_staging_recovery_uses_smtp_only_for_explicitly_allowed_account(): void
    {
        $user = LegacyUser::findOrFail(7);
        config()->set('app.env', 'staging');
        config()->set('mail.default', 'log');
        config()->set('ccyf.staging_recovery_smtp_emails', 'otra@example.test, PRUEBA@example.test');

        $this->assertSame('smtp', (new CcyfResetPassword('token'))->toMail($user)->mailer);

        config()->set('ccyf.staging_recovery_smtp_emails', 'otra@example.test');
        $this->assertNull((new CcyfResetPassword('token'))->toMail($user)->mailer);

        config()->set('app.env', 'local');
        config()->set('ccyf.staging_recovery_smtp_emails', 'prueba@example.test');
        $this->assertNull((new CcyfResetPassword('token'))->toMail($user)->mailer);
    }

    public function test_recovery_mail_is_in_spanish_with_an_inline_institutional_header(): void
    {
        $message = (new CcyfResetPassword('test-token'))->toMail(LegacyUser::findOrFail(7));
        $html = view($message->view['html'], $message->viewData)->render();
        $plain = view($message->view['text'], $message->viewData)->render();

        $this->assertStringContainsString('Restablece tu contraseña', $html);
        $this->assertStringContainsString('cid:cabecera-institucional@ccyf.cobaemex.edu.mx', $html);
        $this->assertStringContainsString('Gobierno del Estado de México', $html);
        $this->assertStringContainsString('Si no solicitaste este cambio', $plain);
        $this->assertStringNotContainsString('Regards', $html);

        $email = new Email;
        foreach ($message->callbacks as $callback) {
            $callback($email);
        }
        $this->assertCount(1, $email->getAttachments());
        $this->assertSame('cabecera-institucional@ccyf.cobaemex.edu.mx', $email->getAttachments()[0]->getContentId());
        $this->assertSame('inline', $email->getAttachments()[0]->getDisposition());
    }

    public function test_recovery_reports_mail_failure_without_server_error(): void
    {
        config()->set('logging.default', 'null');
        Password::shouldReceive('broker')->once()->andThrow(new \RuntimeException('SMTP unavailable'));

        $this->post('/recuperar-acceso', ['email' => 'prueba@example.test'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }
}
