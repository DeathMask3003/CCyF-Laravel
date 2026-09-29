<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\MexicanPhone;
use App\Services\ProfilePhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ProfileLocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);
        DB::table('ccyf_usuarios')->insert([
            ['usu_id' => 1, 'usu_area' => 'Administradora Ejemplo', 'usu_correo' => 'admin@example.test', 'usu_pass' => Hash::make('ClaveVieja123'), 'rol_id' => 18, 'usu_telf' => '5217222123456', 'est' => 1],
            ['usu_id' => 2, 'usu_area' => 'Concursante Ejemplo', 'usu_correo' => 'concursante@example.test', 'usu_pass' => Hash::make('ClaveVieja123'), 'rol_id' => 1, 'usu_telf' => '5217222123457', 'est' => 1],
        ]);
    }

    public function test_profile_displays_ten_digits_and_stores_mexican_prefix(): void
    {
        $this->be(LegacyUser::findOrFail(1));
        $this->get('/mi-perfil')->assertOk()->assertSee('value="7222123456"', false)->assertDontSee('value="5217222123456"', false);
        $this->put('/mi-perfil', ['nombre' => 'Administradora Ejemplo', 'telefono' => '7221234567', 'rfc' => '', 'curp' => '', 'ine_clave' => '', 'telefono_alterno' => '7229876543'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_usuarios', ['usu_id' => 1, 'usu_telf' => '5217221234567', 'telf_alter' => '5217229876543']);
        $this->get('/mi-perfil')->assertSee('value="7221234567"', false)->assertSee('value="7229876543"', false);
        $this->assertSame('7222123456', MexicanPhone::local('+52 1 722 212 3456'));
    }

    public function test_profile_rejects_invalid_identity_and_phone_without_changing_data(): void
    {
        $this->be(LegacyUser::findOrFail(2));
        $this->put('/mi-perfil', ['nombre' => 'Concursante Ejemplo', 'telefono' => '5217222123457', 'rfc' => 'MAL', 'curp' => 'MAL', 'ine_clave' => 'MAL'])
            ->assertSessionHasErrors(['telefono', 'rfc', 'curp', 'ine_clave']);
        $this->assertDatabaseHas('ccyf_usuarios', ['usu_id' => 2, 'usu_telf' => '5217222123457']);
    }

    public function test_password_requires_current_password_and_confirmation(): void
    {
        $this->be(LegacyUser::findOrFail(1));
        $this->put('/mi-perfil/contrasena', ['current_password' => 'incorrecta', 'password' => 'ClaveNueva123', 'password_confirmation' => 'ClaveNueva123'])
            ->assertSessionHasErrors('current_password');
        $this->put('/mi-perfil/contrasena', ['current_password' => 'ClaveVieja123', 'password' => 'ClaveNueva123', 'password_confirmation' => 'otra'])
            ->assertSessionHasErrors('password');
        $this->put('/mi-perfil/contrasena', ['current_password' => 'ClaveVieja123', 'password' => 'ClaveNueva123', 'password_confirmation' => 'ClaveNueva123'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('ClaveNueva123', DB::table('ccyf_usuarios')->where('usu_id', 1)->value('usu_pass')));
    }

    public function test_staff_can_upload_and_view_a_signature_but_contestants_cannot(): void
    {
        Storage::fake('local');
        $root = Storage::disk('local')->path('e-signs');
        mkdir($root, 0777, true);
        config()->set('ccyf.legacy_signatures_root', $root);

        $this->be(LegacyUser::findOrFail(1));
        $this->get('/mi-perfil')->assertOk()->assertSee('Mi firma')->assertSee('Cargar imagen de firma');
        $this->get('/mi-perfil/firma')->assertNotFound();
        $this->post('/mi-perfil/firma', [
            'firma' => UploadedFile::fake()->create('archivo.php', 1, 'application/x-php'),
        ])->assertSessionHasErrors('firma');
        $this->post('/mi-perfil/firma', [
            'firma' => UploadedFile::fake()->image('firma.png', 300, 120),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFileExists($root.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.'1'
            .DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'firma.png');
        $this->get('/mi-perfil/firma')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/mi-perfil')->assertOk()->assertSee('/mi-perfil/firma', false);

        $this->be(LegacyUser::findOrFail(2));
        $this->get('/mi-perfil')->assertOk()->assertDontSee('Cargar imagen de firma');
        $this->get('/mi-perfil/firma')->assertForbidden();
        $this->post('/mi-perfil/firma', [
            'firma' => UploadedFile::fake()->image('otra.png', 300, 120),
        ])->assertForbidden();
        $this->assertFileDoesNotExist($root.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.'2'
            .DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'firma.png');
    }

    public function test_locations_are_private_and_coordinates_are_validated(): void
    {
        $this->be(LegacyUser::findOrFail(1));
        $this->postJson('/ubicaciones', ['latitud' => 100, 'longitud' => -99])->assertUnprocessable();
        $this->postJson('/ubicaciones', ['latitud' => 19.285, 'longitud' => -99.654, 'precision_gps' => 20])->assertCreated();
        $this->assertDatabaseHas('ccyf_ubicaciones', ['usu_id' => 1, 'fuente' => 'Navegador']);
        $this->get('/ubicaciones')->assertOk()->assertSee('19.285000');
        $this->get('/mi-perfil')->assertOk()->assertSee('Coordenadas registradas');
        $this->be(LegacyUser::findOrFail(2));
        $this->get('/ubicaciones')->assertOk()->assertDontSee('19.285000');
    }

    public function test_profile_photo_is_private_and_can_be_replaced(): void
    {
        Storage::fake('local');
        $this->be(LegacyUser::findOrFail(1));
        $this->get('/mi-perfil/foto')->assertNotFound();
        $this->post('/mi-perfil/foto', ['foto' => UploadedFile::fake()->image('retrato.jpg', 800, 600)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $path = Storage::disk('local')->path('profile-photos/1.png');
        $this->assertFileExists($path);
        $this->assertSame([420, 420], array_slice(getimagesize($path), 0, 2));
        $this->post('/mi-perfil/foto', ['foto' => UploadedFile::fake()->image('nuevo.png', 600, 800)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFileExists($path);
        $this->get('/mi-perfil/foto')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/mi-perfil')->assertSee('/mi-perfil/foto', false);
        $this->post('/mi-perfil/foto', ['foto' => UploadedFile::fake()->create('archivo.php', 1)])
            ->assertSessionHasErrors('foto');
        $this->assertFileExists($path);
    }

    public function test_sat_signature_validates_files_and_physically_deletes_local_and_legacy_copies(): void
    {
        Storage::fake('local');
        config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('legacy');
        DB::table('ccyf_roles')->insert([
            ['rol_id' => 18, 'rol_nom' => 'Administrador', 'est' => 1],
            ['rol_id' => 1, 'rol_nom' => 'Concursante', 'est' => 1],
        ]);
        Schema::connection('legacy')->create('tm_efirmas', function (Blueprint $table): void {
            $table->integer('usu_id')->primary();
            foreach (['alias', 'cer_path', 'key_path', 'serial_cert', 'valid_from', 'valid_to', 'method'] as $column) {
                $table->string($column)->nullable();
            }
        });
        DB::table('ccyf_usuarios')->where('usu_id', 1)->update(['legacy_usu_id' => 77]);
        $legacyRoot = Storage::disk('local')->path('original-e-signs');
        $legacySecure = $legacyRoot.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.'77'.DIRECTORY_SEPARATOR.'secure';
        mkdir($legacySecure, 0700, true);
        config()->set('ccyf.legacy_signatures_root', $legacyRoot);
        $opensslConfig = Storage::disk('local')->path('openssl-test.cnf');
        file_put_contents($opensslConfig, "[ req ]\ndefault_bits = 2048\ndistinguished_name = dn\nprompt = no\n[ dn ]\nCN = Prueba\n");
        $config = ['config' => $opensslConfig, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $private = openssl_pkey_new($config);
        $csr = openssl_csr_new(['commonName' => 'Firmante de prueba'], $private, $config);
        $cert = openssl_csr_sign($csr, null, $private, 365, $config);
        $this->assertTrue(openssl_x509_export($cert, $certPem));
        $this->assertTrue(openssl_pkey_export($private, $keyPem, 'ClaveSAT123', $config));
        file_put_contents($legacySecure.DIRECTORY_SEPARATOR.'certificado.pem', $certPem);
        file_put_contents($legacySecure.DIRECTORY_SEPARATOR.'clave.pem', $keyPem);
        DB::connection('legacy')->table('tm_efirmas')->insert(['usu_id' => 77, 'alias' => 'SAT original',
            'cer_path' => $legacySecure.DIRECTORY_SEPARATOR.'certificado.pem',
            'key_path' => $legacySecure.DIRECTORY_SEPARATOR.'clave.pem', 'method' => 'efirma']);

        $this->be(LegacyUser::findOrFail(1));
        $this->get('/mi-perfil')->assertOk()->assertSee('SAT original');
        $this->post('/mi-perfil/efirma', ['cer' => $this->satUpload('certificado.cer', $certPem),
            'key' => $this->satUpload('clave.key', $keyPem), 'password_sat' => 'Incorrecta'])
            ->assertSessionHasErrors('key');
        $this->post('/mi-perfil/efirma', ['cer' => $this->satUpload('certificado.cer', $certPem),
            'key' => $this->satUpload('clave.key', $keyPem), 'password_sat' => 'ClaveSAT123', 'alias' => 'SAT nuevo'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $localFolder = Storage::disk('local')->path('sat-signatures/users/1');
        $this->assertCount(2, glob($localFolder.DIRECTORY_SEPARATOR.'*.pem'));
        $this->get('/mi-perfil')->assertSee('SAT nuevo');
        $this->post('/mi-perfil/efirma', ['cer' => $this->satUpload('certificado.cer', $certPem),
            'key' => $this->satUpload('clave.key', $keyPem), 'password_sat' => 'ClaveSAT123', 'alias' => 'SAT reemplazado'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(2, glob($localFolder.DIRECTORY_SEPARATOR.'*.pem'));
        $this->delete('/mi-perfil/efirma')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], glob($localFolder.DIRECTORY_SEPARATOR.'*.pem'));
        $this->assertFileDoesNotExist($legacySecure.DIRECTORY_SEPARATOR.'certificado.pem');
        $this->assertFileDoesNotExist($legacySecure.DIRECTORY_SEPARATOR.'clave.pem');
        $this->assertDatabaseHas('tm_efirmas', ['usu_id' => 77, 'cer_path' => null, 'key_path' => null], 'legacy');
        $this->get('/mi-perfil')->assertDontSee('SAT nuevo');

        $this->be(LegacyUser::findOrFail(2));
        $this->get('/mi-perfil')->assertDontSee('e.firma del SAT');
        $this->post('/mi-perfil/efirma', [])->assertForbidden();
        $this->delete('/mi-perfil/efirma')->assertForbidden();
    }

    public function test_profile_photo_uses_existing_legacy_image_when_no_new_photo_was_uploaded(): void
    {
        Storage::fake('local');
        config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('legacy');
        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->integer('usu_id')->primary();
            $table->string('usu_img')->nullable();
        });
        DB::table('ccyf_usuarios')->where('usu_id', 1)->update(['legacy_usu_id' => 77]);
        DB::connection('legacy')->table('tm_usuario')->insert(['usu_id' => 77, 'usu_img' => 'foto-prueba.png']);
        $folder = Storage::disk('local')->path('legacy-images');
        mkdir($folder, 0700, true);
        config()->set('ccyf.legacy_profile_photos_root', $folder);
        $path = $folder.DIRECTORY_SEPARATOR.'foto-prueba.png';
        $image = UploadedFile::fake()->image('original.png', 200, 200);
        copy($image->getRealPath(), $path);
        $this->assertSame(realpath($path), app(ProfilePhotos::class)->pathFor(LegacyUser::findOrFail(1)));
    }

    private function satUpload(string $name, string $contents): UploadedFile
    {
        $path = Storage::disk('local')->path('sat-test-'.uniqid());
        file_put_contents($path, $contents);
        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_historical_profile_and_locations_import_once_without_replacing_edits(): void
    {
        config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('legacy');
        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->integer('usu_id')->primary();
            foreach (['rfc', 'ine', 'ine2', 'direcc', 'cont_alter', 'telf_alter', 'telegram_chat_id'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::connection('legacy')->create('tm_ubicaciones', function (Blueprint $table): void {
            $table->integer('ubic_id')->primary();
            $table->integer('usu_id');
            $table->decimal('latitud');
            $table->decimal('longitud');
            $table->decimal('precision_gps')->nullable();
            $table->boolean('es_aproximada')->default(true);
            foreach (['ciudad', 'region', 'pais', 'ip', 'fuente'] as $column) {
                $table->string($column)->nullable();
            }
            $table->dateTime('fecha_registro')->nullable();
            $table->integer('estado')->default(1);
        });
        DB::table('ccyf_usuarios')->where('usu_id', 1)->update(['legacy_usu_id' => 7]);
        DB::connection('legacy')->table('tm_usuario')->insert(['usu_id' => 7, 'rfc' => 'ABCD8001011A2', 'ine' => 'ABCD800101HMCLRS09', 'ine2' => 'LEGADO1234', 'telegram_chat_id' => 'Sin Registrar']);
        DB::connection('legacy')->table('tm_ubicaciones')->insert(['ubic_id' => 3, 'usu_id' => 7, 'latitud' => 19.25, 'longitud' => -99.6, 'fecha_registro' => '2026-01-02 10:00:00']);

        $this->artisan('ccyf:import-profiles-locations')->assertSuccessful();
        $this->assertDatabaseHas('ccyf_usuarios', ['usu_id' => 1, 'rfc' => 'ABCD8001011A2', 'curp' => 'ABCD800101HMCLRS09', 'ine_clave' => 'LEGADO1234', 'telegram_chat_id' => null]);
        $this->assertDatabaseHas('ccyf_ubicaciones', ['legacy_ubic_id' => 3, 'usu_id' => 1]);
        $this->be(LegacyUser::findOrFail(1));
        $this->put('/mi-perfil', ['nombre' => 'Administradora Ejemplo', 'telefono' => '7222123456', 'rfc' => 'ABCD8001011A2', 'curp' => 'ABCD800101HMCLRS09', 'ine_clave' => 'LEGADO1234'])
            ->assertRedirect()->assertSessionHasNoErrors();
        DB::table('ccyf_usuarios')->where('usu_id', 1)->update(['rfc' => 'EDITADO']);
        $this->artisan('ccyf:import-profiles-locations')->assertSuccessful();
        $this->assertDatabaseHas('ccyf_usuarios', ['usu_id' => 1, 'rfc' => 'EDITADO']);
        $this->assertDatabaseCount('ccyf_ubicaciones', 1);
    }
}
