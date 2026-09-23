<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\DocumentFiles;
use App\Services\PrevaluationRecords;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.legacy', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
        DB::purge('sqlite'); DB::purge('legacy');
        $this->artisan('migrate', ['--database'=>'sqlite','--force'=>true]);

        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->integer('usu_id')->primary(); $table->string('usu_area'); $table->string('usu_correo');
            $table->string('usu_telf')->nullable(); $table->string('rfc')->nullable();
            $table->string('ine')->nullable(); $table->string('ine2')->nullable();
        });
        Schema::connection('legacy')->create('tm_categoria_widi', function (Blueprint $table): void {
            $table->integer('cat_id')->primary(); $table->string('cat_nom');
        });
        Schema::connection('legacy')->create('tm_documento', function (Blueprint $table): void {
            $table->integer('doc_id')->primary(); $table->integer('usu_id'); $table->integer('trami_id');
            $table->integer('num_doc'); $table->string('doc_exter'); $table->string('doc_estado'); $table->dateTime('fech_crea');
        });
        Schema::connection('legacy')->create('td_documentov3', function (Blueprint $table): void {
            $table->integer('det_id')->primary(); $table->integer('doc_id'); $table->integer('est');
            $table->string('prop_escrito')->nullable();
        });
        DB::connection('legacy')->table('tm_usuario')->insert([
            ['usu_id'=>7,'usu_area'=>'Permisionario Uno','usu_correo'=>'uno@example.test','rfc'=>'RFCUNO','ine'=>'CURPUNO','ine2'=>'INEUNO'],
            ['usu_id'=>8,'usu_area'=>'Permisionario Dos','usu_correo'=>'dos@example.test','rfc'=>'RFCDOS','ine'=>'CURPDOS','ine2'=>'INEDOS'],
        ]);
        DB::connection('legacy')->table('tm_categoria_widi')->insert(['cat_id'=>9,'cat_nom'=>'Convocatoria actual']);
        DB::connection('legacy')->table('tm_documento')->insert([
            ['doc_id'=>80,'usu_id'=>7,'trami_id'=>3,'num_doc'=>9,'doc_exter'=>'Plantel Centro','doc_estado'=>'Finalizado','fech_crea'=>'2026-09-01 10:00:00'],
            ['doc_id'=>81,'usu_id'=>8,'trami_id'=>4,'num_doc'=>9,'doc_exter'=>'Plantel Norte','doc_estado'=>'Finalizado','fech_crea'=>'2026-09-02 10:00:00'],
        ]);
        DB::connection('legacy')->table('td_documentov3')->insert(['det_id'=>1,'doc_id'=>80,'est'=>1,'prop_escrito'=>'original.pdf']);
        foreach ([1=>'Concursante',18=>'Administrador'] as $id=>$name) {
            DB::table('ccyf_roles')->insert(['rol_id'=>$id,'legacy_rol_id'=>$id,'rol_nom'=>$name,'est'=>1]);
        }
        foreach ([7=>1,8=>1,18=>18] as $id=>$role) {
            DB::table('ccyf_usuarios')->insert(['usu_id'=>$id,'legacy_usu_id'=>$id,'rol_id'=>$role,
                'usu_area'=>'Usuario '.$id,'usu_correo'=>$id.'@example.test','usu_pass'=>Hash::make('Clave123456'),'est'=>1]);
        }
        DB::table('ccyf_role_permissions')->insert(['rol_id'=>18,'menu_key'=>'actualiza_docs','allowed'=>1]);
        Storage::fake('local');
        Storage::disk('local')->put('assets/documents/80/original.pdf', '%PDF-1.4 original');
        config()->set('ccyf.legacy_files_root', Storage::disk('local')->path('assets/documents'));
    }

    public function test_owner_sees_only_own_records_and_admin_sees_both_services(): void
    {
        $this->be(LegacyUser::findOrFail(7));
        $this->get('/actualizacion-documentacion')->assertOk()->assertSee('Usuario 7')
            ->assertDontSee('Usuario 8')->assertSee('CURPUNO')->assertSee('RFCUNO');
        $this->get('/actualizacion-documentacion/historico-80')->assertOk()->assertSee('Propuesta por escrito');
        $this->get('/actualizacion-documentacion/historico-81')->assertForbidden();
        $this->be(LegacyUser::findOrFail(18));
        $this->get('/actualizacion-documentacion?servicio=fotocopiado')->assertOk()
            ->assertSee('Usuario 8')->assertDontSee('Usuario 7');
    }

    public function test_photo_only_participant_opens_the_service_with_their_records(): void
    {
        $this->be(LegacyUser::findOrFail(8));
        $this->get('/actualizacion-documentacion')->assertOk()
            ->assertSee('Fotocopiado')->assertSee('Usuario 8')
            ->assertDontSee('Usuario 7');
    }

    public function test_upload_keeps_original_and_serves_latest_with_history(): void
    {
        $this->be(LegacyUser::findOrFail(7));
        $this->post('/actualizacion-documentacion/historico-80', ['files'=>[
            'prop_escrito'=>UploadedFile::fake()->create('nueva-propuesta.pdf', 120, 'application/pdf'),
        ]])->assertRedirect('/actualizacion-documentacion/historico-80')->assertSessionHasNoErrors();
        $this->assertSame('original.pdf', DB::connection('legacy')->table('td_documentov3')->value('prop_escrito'));
        $this->assertDatabaseHas('ccyf_document_updates', ['origin'=>'historico','registration_id'=>80,
            'field_key'=>'prop_escrito','original_name'=>'nueva-propuesta.pdf','uploaded_by'=>7]);
        $version = DB::table('ccyf_document_updates')->first();
        Storage::disk('local')->assertExists($version->path);
        $this->assertSame($version->id, app(DocumentFiles::class)->effective('historico',80,'prop_escrito')['version']);
        $this->assertSame(Storage::disk('local')->path($version->path),
            app(PrevaluationRecords::class)->filePath((object) ['origen'=>'historico','registro_id'=>80,'servicio'=>'cafeteria'], 'prop_escrito'));
        $this->get('/actualizacion-documentacion/historico-80')->assertOk()->assertSee('Historial')
            ->assertSee('nueva-propuesta.pdf')->assertSee('Archivo original');
        $this->get('/actualizacion-documentacion/historico-80/archivos/prop_escrito')->assertOk()
            ->assertHeader('Content-Type','application/pdf');
        $this->get('/actualizacion-documentacion/historico-80/versiones/'.$version->id)->assertOk();
        $this->get('/actualizacion-documentacion/historico-80/original/prop_escrito')->assertOk()
            ->assertHeader('Content-Type','application/pdf');
        DB::table('ccyf_role_permissions')->insert(['rol_id'=>18,'menu_key'=>'buscarOficio','allowed'=>1]);
        $this->be(LegacyUser::findOrFail(18));
        $reviewFile = $this->get('/convocatorias-finalizadas/historico/80/archivo/prop_escrito')->assertOk();
        $this->assertSame(Storage::disk('local')->path($version->path), $reviewFile->baseResponse->getFile()->getPathname());
        $this->be(LegacyUser::findOrFail(8));
        $this->get('/actualizacion-documentacion/historico-80/versiones/'.$version->id)->assertForbidden();
    }

    public function test_rejects_foreign_field_and_executable_filename(): void
    {
        $this->be(LegacyUser::findOrFail(7));
        $this->post('/actualizacion-documentacion/historico-80', ['files'=>[
            'campo_ajeno'=>UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf'),
        ]])->assertSessionHasErrors('files');
        $this->post('/actualizacion-documentacion/historico-80', ['files'=>[
            'prop_escrito'=>UploadedFile::fake()->create('archivo.php', 10, 'application/pdf'),
        ]])->assertSessionHasErrors('files.prop_escrito');
        $this->assertDatabaseCount('ccyf_document_updates', 0);
    }

    public function test_new_registration_uses_dynamic_document_requirement(): void
    {
        $service = DB::table('ccyf_tipos_servicio')->insertGetId([
            'legacy_trami_id'=>3,'nombre'=>'Cafetería','nombre_clave'=>'cafeteria','descripcion'=>'Servicio de cafetería',
        ]);
        $campus = DB::table('ccyf_planteles')->insertGetId(['nombre'=>'Plantel Nuevo','nombre_clave'=>'plantel-nuevo']);
        $call = DB::table('ccyf_convocatorias')->insertGetId([
            'numero'=>'Convocatoria nueva','numero_clave'=>'convocatoria-nueva','servicio_id'=>$service,
        ]);
        $catalog = DB::table('ccyf_catalogos')->insertGetId(['tipo'=>'cafeteria','convocatoria_id'=>$call]);
        $type = DB::table('ccyf_tipos_documento')->insertGetId(['nombre'=>'Solicitud','nombre_clave'=>'solicitud']);
        $requirement = DB::table('ccyf_requisitos_documento')->insertGetId([
            'servicio_id'=>$service,'clave'=>'solicitud','nombre'=>'Solicitud firmada','orden'=>1,'activo'=>1,
        ]);
        $registration = DB::table('ccyf_registros')->insertGetId([
            'convocatoria_id'=>$call,'catalogo_id'=>$catalog,'servicio_id'=>$service,'plantel_id'=>$campus,
            'tipo_documento_id'=>$type,'usu_id'=>7,'solicitante'=>'Participante nuevo','dirigido_a'=>'Comité',
            'comentarios'=>'Participación','estado'=>'Recibido','enviado_at'=>'2026-09-22 12:00:00',
        ]);
        Storage::disk('local')->put('registros/nuevo/solicitud.pdf', '%PDF-1.4 solicitud');
        DB::table('ccyf_registro_archivos')->insert([
            'registro_id'=>$registration,'requisito_id'=>$requirement,'nombre_original'=>'solicitud.pdf',
            'ruta'=>'registros/nuevo/solicitud.pdf','mime'=>'application/pdf','bytes'=>18,
        ]);

        $this->be(LegacyUser::findOrFail(7));
        $this->get('/actualizacion-documentacion/actual-'.$registration)->assertOk()
            ->assertSee('Solicitud firmada')->assertSee('Participante nuevo');
        $this->post('/actualizacion-documentacion/actual-'.$registration, ['files'=>[
            'req-'.$requirement=>UploadedFile::fake()->create('solicitud-actualizada.pdf', 100, 'application/pdf'),
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $current = app(DocumentFiles::class)->effective('actual', $registration, 'req-'.$requirement);
        $this->assertSame('solicitud-actualizada.pdf', $current['name']);
        $this->get('/actualizacion-documentacion/actual-'.$registration.'/original/req-'.$requirement)->assertOk();
    }
}
