<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PermitTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::purge('legacy');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);

        Schema::connection('legacy')->create('tm_categoria_widi', function (Blueprint $table): void {
            $table->integer('cat_id')->primary(); $table->string('cat_nom');
        });
        Schema::connection('legacy')->create('tm_areas', function (Blueprint $table): void {
            $table->integer('area_id')->primary(); $table->string('area_nom');
        });
        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->integer('usu_id')->primary();
            foreach (['usu_area', 'usu_correo', 'usu_telf', 'ine', 'direcc'] as $field) $table->string($field)->nullable();
        });
        Schema::connection('legacy')->create('tm_documento', function (Blueprint $table): void {
            $table->integer('doc_id')->primary(); $table->integer('trami_id'); $table->integer('usu_id');
            $table->string('doc_exter'); $table->date('doc_fech_ini'); $table->date('doc_fech_fin');
            $table->dateTime('fech_crea'); $table->integer('est'); $table->integer('num_doc');
        });
        Schema::connection('legacy')->create('tm_seguimiento_permisio', function (Blueprint $table): void {
            $table->integer('detapermi_id')->primary(); $table->integer('doc_id')->nullable(); $table->integer('est');
            $table->decimal('metros_cuadrados')->nullable(); $table->string('matricula')->nullable(); $table->decimal('monto')->nullable();
            foreach (['convocatoria1', 'convocatoria2', 'convocatoria3', 'pagosalmes', 'construidapor', 'servicioenergia', 'observaciones'] as $field) $table->string($field)->nullable();
            $table->dateTime('fech_crea')->nullable(); $table->dateTime('fech_modif')->nullable();
        });
        Schema::connection('legacy')->create('td_archivos_permisio', function (Blueprint $table): void {
            $table->integer('archivo_id')->primary(); $table->integer('detapermi_id'); $table->integer('est');
            foreach (range(1, 5) as $number) $table->string('archivo'.$number)->nullable();
            $table->dateTime('fech_crea')->nullable();
        });
        Schema::connection('legacy')->create('tm_mennu', function (Blueprint $table): void {
            $table->integer('men_id')->primary(); $table->string('men_nom'); $table->integer('est');
        });
        Schema::connection('legacy')->create('td_medu_detalle', function (Blueprint $table): void {
            $table->integer('rol_id'); $table->integer('men_id'); $table->string('mend_permi');
        });

        DB::table('ccyf_roles')->insert([
            ['rol_id' => 18, 'rol_nom' => 'Administrador', 'legacy_rol_id' => 18, 'est' => 1],
            ['rol_id' => 1, 'rol_nom' => 'Concursante', 'legacy_rol_id' => 1, 'est' => 1],
        ]);
        DB::table('ccyf_role_permissions')->insert(['rol_id' => 18, 'menu_key' => 'seguimiento_permisionarios', 'allowed' => 1]);
        DB::table('ccyf_usuarios')->insert(['usu_id' => 18, 'usu_area' => 'Administradora', 'usu_correo' => 'admin@example.test', 'usu_pass' => Hash::make('Clave123456'), 'rol_id' => 18, 'est' => 1]);
        DB::table('ccyf_usuarios')->insert(['usu_id' => 7, 'legacy_usu_id' => 7, 'usu_area' => 'Permisionario Actual', 'usu_correo' => 'perm@example.test', 'usu_telf' => '5217221234567', 'curp' => 'ABCD800101HMCLRS09', 'usu_pass' => Hash::make('Clave123456'), 'rol_id' => 1, 'est' => 1]);
        DB::connection('legacy')->table('tm_mennu')->insert(['men_id' => 90, 'men_nom' => 'seguimiento_permisionarios', 'est' => 1]);
        DB::connection('legacy')->table('td_medu_detalle')->insert(['rol_id' => 18, 'men_id' => 90, 'mend_permi' => 'si']);
        DB::connection('legacy')->table('tm_categoria_widi')->insert(['cat_id' => 9, 'cat_nom' => 'Novena convocatoria']);
        DB::connection('legacy')->table('tm_areas')->insert(['area_id' => 1, 'area_nom' => 'Plantel Centro']);
        DB::connection('legacy')->table('tm_usuario')->insert(['usu_id' => 7, 'usu_area' => 'Nombre anterior', 'usu_correo' => 'old@example.test', 'ine' => 'ABCD800101HMCLRS09']);
        DB::connection('legacy')->table('tm_documento')->insert([
            ['doc_id' => 81, 'trami_id' => 3, 'usu_id' => 7, 'doc_exter' => 'Plantel Centro', 'num_doc' => 9, 'doc_fech_ini' => '2026-09-01', 'doc_fech_fin' => '2027-08-31', 'fech_crea' => '2026-06-01 12:00:00', 'est' => 0],
            ['doc_id' => 82, 'trami_id' => 4, 'usu_id' => 7, 'doc_exter' => 'Plantel Centro', 'num_doc' => 9, 'doc_fech_ini' => '2026-07-01', 'doc_fech_fin' => '2027-06-30', 'fech_crea' => '2026-06-02 12:00:00', 'est' => 0],
        ]);
    }

    public function test_two_service_tabs_keep_original_columns_and_filters(): void
    {
        $this->be(LegacyUser::findOrFail(18));
        $this->get('/seguimiento-permisionarios')->assertOk()->assertSee('Cafetería')->assertSee('Fotocopiado')
            ->assertSee('CURP del Permisionario')->assertSee('Dirección del Permisionario')
            ->assertSee('Validez del Contrato')->assertSee('Permisionario Actual')->assertDontSee('historico-82')
            ->assertSee('/seguimiento-permisionarios/exportar/xlsx?servicio=cafeteria')
            ->assertSee('/seguimiento-permisionarios/exportar/pdf?servicio=cafeteria');
        $this->get('/seguimiento-permisionarios?servicio=fotocopiado')->assertOk()->assertSee('historico-82')->assertDontSee('historico-81');
        $this->get('/seguimiento-permisionarios?servicio=cafeteria&mes_inicio=2026-07')->assertOk()->assertSee('No se encontraron permisionarios');
        $this->get('/seguimiento-permisionarios?servicio=cafeteria&buscar=Permisionario%20Actual')->assertOk()->assertSee('historico-81');
    }

    public function test_followup_save_renewal_files_and_permissions(): void
    {
        Storage::fake('local');
        $this->be(LegacyUser::findOrFail(18));
        $this->put('/seguimiento-permisionarios/historico-81', [
            'servicio' => 'cafeteria', 'metros_cuadrados' => '42.50', 'matricula' => 'MAT-25', 'monto' => '8000.00',
            'pagosalmes' => 'Al corriente', 'archivo1' => UploadedFile::fake()->create('contrato.pdf', 20, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_seguimientos', ['origen' => 'historico', 'registro_id' => 81, 'matricula' => 'MAT-25']);
        $this->assertDatabaseHas('ccyf_seguimiento_archivos', ['numero' => 1, 'origen' => 'local']);
        $this->get('/seguimiento-permisionarios/historico-81/archivos/1')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        Storage::disk('local')->put('assets/documentos_permisonarios/contrato-anterior.pdf', '%PDF-1.4 historial');
        config()->set('ccyf.legacy_root', Storage::disk('local')->path(''));
        DB::table('ccyf_seguimiento_archivos')->insert([
            'seguimiento_id' => DB::table('ccyf_seguimientos')->where('registro_id', 81)->value('id'),
            'numero' => 2, 'origen' => 'historico', 'ruta' => 'assets/documentos_permisonarios/contrato-anterior.pdf',
            'nombre' => 'contrato-anterior.pdf', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get('/seguimiento-permisionarios/historico-81/archivos/2')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->put('/seguimiento-permisionarios/historico-81/renovar', ['servicio' => 'cafeteria', 'fecha_inicio' => '2027-09-01', 'fecha_fin' => '2028-08-31'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_seguimientos', ['registro_id' => 81, 'fecha_fin_renovada' => '2028-08-31']);
        $this->assertSame('2027-08-31', DB::connection('legacy')->table('tm_documento')->where('doc_id', 81)->value('doc_fech_fin'));
        $this->be(LegacyUser::findOrFail(7));
        $this->get('/seguimiento-permisionarios')->assertForbidden();
        $this->put('/seguimiento-permisionarios/historico-81', ['metros_cuadrados' => 5])->assertForbidden();
    }

    public function test_historical_import_is_idempotent_and_respects_local_edits(): void
    {
        DB::connection('legacy')->table('tm_seguimiento_permisio')->insert([
            'detapermi_id' => 5, 'doc_id' => 81, 'est' => 1, 'metros_cuadrados' => 35, 'matricula' => 'ORIGINAL', 'monto' => 9000,
        ]);
        DB::table('ccyf_role_permissions')->where('menu_key', 'seguimiento_permisionarios')->delete();
        $this->artisan('ccyf:import-permisionarios')->assertSuccessful();
        $this->assertDatabaseHas('ccyf_seguimientos', ['legacy_detapermi_id' => 5, 'matricula' => 'ORIGINAL']);
        $this->assertDatabaseHas('ccyf_role_permissions', ['rol_id' => 18, 'menu_key' => 'seguimiento_permisionarios', 'allowed' => 1]);
        DB::table('ccyf_seguimientos')->where('legacy_detapermi_id', 5)->update(['matricula' => 'EDITADO']);
        $this->artisan('ccyf:import-permisionarios')->assertSuccessful();
        $this->assertDatabaseHas('ccyf_seguimientos', ['legacy_detapermi_id' => 5, 'matricula' => 'EDITADO']);
        $this->assertDatabaseCount('ccyf_seguimientos', 1);
    }

    public function test_exports_and_expedient_contain_filtered_service(): void
    {
        $this->be(LegacyUser::findOrFail(18));
        $csv = $this->get('/seguimiento-permisionarios/exportar/csv?servicio=cafeteria');
        $csv->assertOk()->assertDownload();
        $this->assertStringContainsString('Permisionario Actual', $csv->streamedContent());
        $this->assertStringNotContainsString('82 -', $csv->streamedContent());
        $pdf = $this->get('/seguimiento-permisionarios/exportar/pdf?servicio=cafeteria');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $xlsx = $this->get('/seguimiento-permisionarios/exportar/xlsx?servicio=fotocopiado');
        $xlsx->assertOk()->assertDownload();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());
        $zip = $this->get('/seguimiento-permisionarios/historico-81/expediente');
        $zip->assertOk()->assertDownload();
        $path = $zip->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path) === true);
        $this->assertNotFalse($archive->locateName('00_INFORMACION/RESUMEN_EXPEDIENTE.txt'));
        $archive->close();
        unlink($path);
    }
}
