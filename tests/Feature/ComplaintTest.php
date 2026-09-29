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

class ComplaintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.legacy', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite'); DB::purge('legacy');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);

        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->integer('usu_id')->primary(); $table->string('usu_area'); $table->string('usu_correo')->nullable();
        });
        Schema::connection('legacy')->create('tm_categoria_widi', function (Blueprint $table): void {
            $table->integer('cat_id')->primary(); $table->string('cat_nom');
        });
        Schema::connection('legacy')->create('tm_documento', function (Blueprint $table): void {
            $table->integer('doc_id')->primary(); $table->integer('usu_id'); $table->integer('trami_id');
            $table->integer('num_doc'); $table->string('doc_exter'); $table->dateTime('fech_crea')->nullable();
        });
        Schema::connection('legacy')->create('tm_permisionario_queja', function (Blueprint $table): void {
            $table->integer('queja_id')->primary(); $table->integer('usu_id'); $table->integer('doc_id');
            $table->text('queja_obs'); $table->string('queja_evidencia')->nullable();
            $table->integer('queja_calificacion'); $table->dateTime('queja_fecha')->nullable();
            $table->integer('queja_estado'); $table->integer('creado_por'); $table->dateTime('creado_en')->nullable();
        });
        Schema::connection('legacy')->create('tm_areas', function (Blueprint $table): void {
            $table->integer('area_id')->primary(); $table->string('area_nom');
        });
        Schema::connection('legacy')->create('tm_permisionario_queja_manual', function (Blueprint $table): void {
            $table->integer('quejam_id')->primary(); $table->integer('usu_id'); $table->integer('area_id');
            $table->string('permisionario'); $table->text('queja'); $table->dateTime('fech_crea')->nullable();
            $table->integer('est');
        });

        foreach ([18 => 'Administrador', 1 => 'Concursante'] as $id => $name) {
            DB::table('ccyf_roles')->insert(['rol_id' => $id, 'legacy_rol_id' => $id, 'rol_nom' => $name, 'est' => 1]);
            DB::table('ccyf_usuarios')->insert(['usu_id' => $id, 'legacy_usu_id' => $id, 'usu_area' => $name,
                'usu_correo' => $id.'@example.test', 'usu_pass' => Hash::make('Clave123456'), 'rol_id' => $id, 'est' => 1]);
        }
        DB::table('ccyf_role_permissions')->insert(['rol_id' => 18, 'menu_key' => 'quejas', 'allowed' => 1]);
        DB::table('ccyf_planteles')->insert(['id' => 23, 'legacy_area_id' => 23, 'nombre' => 'Plantel Centro',
            'nombre_clave' => 'plantel centro', 'activo' => 1]);
        DB::connection('legacy')->table('tm_usuario')->insert([
            ['usu_id' => 4, 'usu_area' => 'Permisionaria Histórica', 'usu_correo' => 'historica@example.test'],
            ['usu_id' => 18, 'usu_area' => 'Administrador', 'usu_correo' => 'admin@example.test'],
        ]);
        DB::connection('legacy')->table('tm_categoria_widi')->insert(['cat_id' => 9, 'cat_nom' => 'Novena convocatoria']);
        DB::connection('legacy')->table('tm_documento')->insert(['doc_id' => 80, 'usu_id' => 4, 'trami_id' => 3,
            'num_doc' => 9, 'doc_exter' => 'Plantel Centro', 'fech_crea' => '2026-06-01 10:00:00']);
        DB::connection('legacy')->table('tm_areas')->insert(['area_id' => 23, 'area_nom' => 'Plantel Centro']);
        DB::connection('legacy')->table('tm_permisionario_queja')->insert(['queja_id' => 5, 'usu_id' => 4, 'doc_id' => 80,
            'queja_obs' => 'Servicio oportuno', 'queja_evidencia' => 'evidencia.pdf', 'queja_calificacion' => 1,
            'queja_fecha' => '2026-06-10 10:00:00', 'queja_estado' => 1, 'creado_por' => 18, 'creado_en' => '2026-06-10 10:00:00']);
        DB::connection('legacy')->table('tm_permisionario_queja_manual')->insert(['quejam_id' => 7, 'usu_id' => 18,
            'area_id' => 23, 'permisionario' => 'Persona Histórica', 'queja' => 'Adeudo en el plantel',
            'fech_crea' => '2026-06-12 10:00:00', 'est' => 1]);
        Storage::fake('local');
        $root = Storage::disk('local')->path('legacy-quejas');
        if (! is_dir($root.DIRECTORY_SEPARATOR.'80')) mkdir($root.DIRECTORY_SEPARATOR.'80', 0777, true);
        file_put_contents($root.DIRECTORY_SEPARATOR.'80'.DIRECTORY_SEPARATOR.'evidencia.pdf', '%PDF-1.4 evidencia');
        config()->set('ccyf.legacy_complaints_root', $root);
    }

    public function test_historical_participation_and_manual_complaint_are_visible_with_evidence(): void
    {
        $this->be(LegacyUser::findOrFail(18));
        $this->get('/observaciones-quejas')->assertOk()->assertSee('Permisionaria Histórica')
            ->assertSee('Plantel Centro')->assertSee('1 favorables');
        $this->get('/observaciones-quejas/participaciones/historico/80')->assertOk()
            ->assertSee('Servicio oportuno')->assertSee('Ver evidencia');
        $this->get('/observaciones-quejas/evidencias/historico/5')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get('/observaciones-quejas?vista=manuales')->assertOk()->assertSee('Persona Histórica')
            ->assertSee('Adeudo en el plantel');
        DB::connection('legacy')->table('tm_permisionario_queja')->where('queja_id', 5)
            ->update(['queja_evidencia' => '../.env']);
        $this->get('/observaciones-quejas/evidencias/historico/5')->assertNotFound();
        $this->be(LegacyUser::findOrFail(1));
        $this->get('/observaciones-quejas')->assertForbidden();
        $this->get('/observaciones-quejas/evidencias/historico/5')->assertForbidden();
    }

    public function test_new_observation_is_tied_to_the_participation_and_private_evidence(): void
    {
        $this->be(LegacyUser::findOrFail(18));
        $this->post('/observaciones-quejas/participaciones/historico/80', [
            'observacion' => 'Adeudo pendiente de aclarar', 'calificacion' => '0',
            'evidencia' => UploadedFile::fake()->create('sustento.pdf', 30, 'application/pdf'),
        ])->assertRedirect();
        $row = DB::table('ccyf_quejas_participacion')->first();
        $this->assertSame('historico', $row->origen);
        $this->assertSame(80, $row->registro_id);
        $this->assertSame(0, (int) $row->calificacion);
        Storage::disk('local')->assertExists($row->evidencia_ruta);
        $this->get('/observaciones-quejas/participaciones/historico/80')->assertOk()
            ->assertSee('Adeudo pendiente de aclarar')->assertSee('Servicio oportuno');
        $this->get('/observaciones-quejas/evidencias/local/'.$row->id)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->post('/observaciones-quejas/participaciones/historico/999', [
            'observacion' => 'No autorizado', 'calificacion' => '1',
        ])->assertNotFound();
        $this->post('/observaciones-quejas/participaciones/historico/80', [
            'observacion' => 'Sin calificación', 'calificacion' => '3',
        ])->assertSessionHasErrors('calificacion');
        $this->assertDatabaseCount('ccyf_quejas_participacion', 1);
    }

    public function test_recent_laravel_registration_is_available_for_observations(): void
    {
        $service = DB::table('ccyf_tipos_servicio')->insertGetId([
            'legacy_trami_id' => 4, 'nombre' => 'Fotocopiado', 'nombre_clave' => 'fotocopiado',
            'descripcion' => 'Servicio de copias',
        ]);
        $call = DB::table('ccyf_convocatorias')->insertGetId([
            'numero' => 'Convocatoria nueva', 'numero_clave' => 'convocatoria-nueva', 'servicio_id' => $service,
        ]);
        $catalog = DB::table('ccyf_catalogos')->insertGetId([
            'convocatoria_id' => $call, 'tipo' => 'fotocopiado',
        ]);
        $type = DB::table('ccyf_tipos_documento')->insertGetId([
            'nombre' => 'Solicitud', 'nombre_clave' => 'solicitud',
        ]);
        $record = DB::table('ccyf_registros')->insertGetId([
            'convocatoria_id' => $call, 'catalogo_id' => $catalog, 'servicio_id' => $service,
            'plantel_id' => 23, 'tipo_documento_id' => $type, 'usu_id' => 1,
            'solicitante' => 'Concursante actual', 'dirigido_a' => 'Dirección', 'comentarios' => '',
            'estado' => 'Recibido', 'enviado_at' => now(),
        ]);
        $this->be(LegacyUser::findOrFail(18));
        $this->get('/observaciones-quejas?servicio=fotocopiado')->assertOk()
            ->assertSee('Concursante')->assertSee('Convocatoria nueva');
        $this->get('/observaciones-quejas/participaciones/actual/'.$record)->assertOk()
            ->assertSee('Plantel Centro');
        $this->post('/observaciones-quejas/participaciones/actual/'.$record, [
            'observacion' => 'Entrega puntual', 'calificacion' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('ccyf_quejas_participacion', [
            'origen' => 'actual', 'registro_id' => $record, 'calificacion' => 1,
        ]);
    }

    public function test_participation_pagination_is_compact_and_preserves_filters(): void
    {
        $users = [];
        $documents = [];
        foreach (range(20, 31) as $id) {
            $users[] = ['usu_id' => $id, 'usu_area' => 'Participante '.$id,
                'usu_correo' => $id.'@example.test'];
            $documents[] = ['doc_id' => $id + 100, 'usu_id' => $id,
                'trami_id' => 3, 'num_doc' => 9, 'doc_exter' => 'Plantel Centro',
                'fech_crea' => '2026-06-01 10:00:00'];
        }
        DB::connection('legacy')->table('tm_usuario')->insert($users);
        DB::connection('legacy')->table('tm_documento')->insert($documents);
        $this->be(LegacyUser::findOrFail(18));

        $page = $this->get('/observaciones-quejas?servicio=cafeteria')->assertOk()
            ->assertSee('complaint-pager')->assertSee('Mostrando')
            ->assertSee('Página siguiente')->assertDontSee('pagination.next');
        $html = new \DOMDocument;
        @$html->loadHTML($page->getContent());
        $next = (new \DOMXPath($html))->query('//nav[@aria-label="Páginas de observaciones y quejas"]//a[@rel="next"]')->item(0);
        $this->assertNotNull($next);
        parse_str((string) parse_url($next->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame('cafeteria', $query['servicio']);
        $this->assertSame('2', $query['page']);

        $this->get($next->getAttribute('href'))->assertOk()
            ->assertSee('Mostrando <strong>13</strong>–<strong>13</strong> de <strong>13</strong> resultados', false);
    }

    public function test_manual_complaints_can_be_created_edited_and_archived(): void
    {
        $this->be(LegacyUser::findOrFail(18));
        $this->get('/observaciones-quejas/manual/nueva')->assertOk()->assertSee('Nueva queja manual');
        $this->post('/observaciones-quejas/manual', ['plantel_id' => 23, 'permisionario' => 'JUAN PÉREZ',
            'queja' => 'Incidencia registrada'])->assertRedirect();
        $row = DB::table('ccyf_quejas_manuales')->first();
        $this->assertSame('Juan Pérez', $row->permisionario);
        $this->put('/observaciones-quejas/manual/'.$row->id, ['plantel_id' => 23,
            'permisionario' => 'Juan Pérez', 'queja' => 'Adeudo por aclarar'])->assertRedirect();
        $this->assertDatabaseHas('ccyf_quejas_manuales', ['id' => $row->id, 'queja' => 'Adeudo por aclarar']);
        $this->patch('/observaciones-quejas/manual/'.$row->id.'/estado')->assertRedirect();
        $this->assertDatabaseHas('ccyf_quejas_manuales', ['id' => $row->id, 'activo' => 0]);
        $this->get('/observaciones-quejas?vista=manuales&estado=inactivas')->assertOk()
            ->assertSee('Adeudo por aclarar');
        $this->post('/observaciones-quejas/manual', ['plantel_id' => 999,
            'permisionario' => 'Juan Pérez', 'queja' => 'No válido'])->assertSessionHasErrors('plantel_id');
    }
}
