<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\DocumentTypes;
use App\Services\PriceCatalogs;
use App\Services\ServiceTypes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PriceCatalogTest extends TestCase
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

        Schema::connection('legacy')->create('tm_usuario', function (Blueprint $table): void {
            $table->increments('usu_id');
            $table->string('usu_area');
            $table->integer('rol_id');
            $table->string('usu_pass');
        });
        Schema::connection('legacy')->create('tm_categoria_widi', function (Blueprint $table): void {
            $table->increments('cat_id');
            $table->string('cat_nom');
            $table->integer('est');
        });
        Schema::connection('legacy')->create('tm_documento', function (Blueprint $table): void {
            $table->increments('doc_id');
            $table->string('num_doc');
            $table->integer('trami_id');
            $table->integer('tipo_id')->nullable();
        });
        Schema::connection('legacy')->create('tm_tipo', function (Blueprint $table): void {
            $table->increments('tipo_id');
            $table->string('tipo_nom', 50);
            $table->integer('est');
            $table->dateTime('fech_crea')->nullable();
            $table->dateTime('fech_modif')->nullable();
        });
        Schema::connection('legacy')->create('tm_tramite', function (Blueprint $table): void {
            $table->increments('trami_id');
            $table->string('trami_nom', 50);
            $table->string('trami_descrip', 200);
            $table->integer('est');
            $table->dateTime('fech_crea')->nullable();
            $table->dateTime('fech_modif')->nullable();
        });
        Schema::connection('legacy')->create('tm_mennu', function (Blueprint $table): void {
            $table->increments('men_id');
            $table->string('men_nom');
            $table->integer('est');
        });
        Schema::connection('legacy')->create('td_medu_detalle', function (Blueprint $table): void {
            $table->increments('mend_id');
            $table->integer('rol_id');
            $table->integer('men_id');
            $table->string('mend_permi');
        });

        DB::connection('legacy')->table('tm_usuario')->insert([
            ['usu_id' => 1, 'usu_area' => 'Administración', 'rol_id' => 18, 'usu_pass' => 'x'],
            ['usu_id' => 2, 'usu_area' => 'Concursante', 'rol_id' => 9, 'usu_pass' => 'x'],
        ]);
        DB::connection('legacy')->table('tm_categoria_widi')->insert([
            ['cat_id' => 8, 'cat_nom' => 'Convocatoria de Cafetería', 'est' => 1],
            ['cat_id' => 9, 'cat_nom' => 'Convocatoria de Fotocopiado', 'est' => 1],
        ]);
        DB::connection('legacy')->table('tm_mennu')->insert([
            ['men_id' => 4, 'men_nom' => 'NuevoOficio', 'est' => 1],
            ['men_id' => 9, 'men_nom' => 'Tipo', 'est' => 1],
            ['men_id' => 10, 'men_nom' => 'Asuntos', 'est' => 1],
            ['men_id' => 16, 'men_nom' => 'Categorias_widi', 'est' => 1],
        ]);
        DB::connection('legacy')->table('td_medu_detalle')->insert([
            ['rol_id' => 18, 'men_id' => 16, 'mend_permi' => 'si'],
            ['rol_id' => 18, 'men_id' => 9, 'mend_permi' => 'si'],
            ['rol_id' => 18, 'men_id' => 10, 'mend_permi' => 'si'],
            ['rol_id' => 9, 'men_id' => 4, 'mend_permi' => 'si'],
        ]);
        DB::connection('legacy')->table('tm_tipo')->insert([
            ['tipo_id' => 1, 'tipo_nom' => 'Convocatoria', 'est' => 1],
            ['tipo_id' => 2, 'tipo_nom' => 'Oficio Externo', 'est' => 0],
        ]);
        DB::connection('legacy')->table('tm_tramite')->insert([
            ['trami_id' => 3, 'trami_nom' => 'Cafetería', 'trami_descrip' => 'Servicio de alimentos', 'est' => 1],
            ['trami_id' => 4, 'trami_nom' => 'Fotocopiado', 'trami_descrip' => 'Servicio de copias', 'est' => 1],
            ['trami_id' => 5, 'trami_nom' => 'Cafetería y Fotocopiado', 'trami_descrip' => 'Servicio combinado', 'est' => 0],
        ]);
        app(DocumentTypes::class)->importLegacy();
        app(ServiceTypes::class)->importLegacy();
    }

    public function test_admin_can_change_one_convocation_without_affecting_another(): void
    {
        $this->asUser(1);
        $this->post('/catalogos/8', ['servicio_id' => 1])->assertRedirect('/catalogos/8');
        $this->post('/catalogos/9', ['servicio_id' => 2])->assertRedirect('/catalogos/9');
        $this->post('/catalogos/8/productos', ['nombre' => 'Fruta picada', 'unidad' => 'vaso'])
            ->assertSessionHasNoErrors();

        $cafeteria = DB::table('ccyf_catalogos')->where('legacy_cat_id', 8)->value('id');
        $fotocopia = DB::table('ccyf_catalogos')->where('legacy_cat_id', 9)->value('id');
        $item = DB::table('ccyf_productos')->where('catalogo_id', $cafeteria)->where('nombre', 'Fruta picada')->first();
        $this->assertNotNull($item);
        $this->assertFalse(DB::table('ccyf_productos')->where('catalogo_id', $fotocopia)->where('nombre', 'Fruta picada')->exists());

        $this->put("/catalogos/8/productos/{$item->id}", [
            'nombre' => 'Fruta picada', 'unidad' => 'vaso', 'orden' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(0, DB::table('ccyf_productos')->where('id', $item->id)->value('activo'));
        $this->get('/nuevo-oficio/8')->assertOk()->assertDontSee('Fruta picada');
        $this->get('/nuevo-oficio/9')->assertOk()->assertSee('Tamaño carta');
    }

    public function test_proposal_draft_uses_only_current_products_and_rejects_extra_prices(): void
    {
        $catalog = app(PriceCatalogs::class)->prepare(8, 1);
        $products = DB::table('ccyf_productos')->where('catalogo_id', $catalog)->pluck('id');
        $prices = $products->mapWithKeys(fn ($id) => [$id => '12.50'])->all();
        $this->asUser(2);

        $this->post('/nuevo-oficio/8/precios', ['tipo_documento_id' => 1, 'precios' => $prices + [99999 => '0.01']])
            ->assertSessionHasErrors('precios');
        $this->assertDatabaseCount('ccyf_precio_borradores', 0);

        $this->post('/nuevo-oficio/8/precios', ['tipo_documento_id' => 1, 'precios' => $prices])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ccyf_precio_borrador_detalles', $products->count());
        $this->assertDatabaseHas('ccyf_precio_borradores', ['tipo_documento_id' => 1, 'usu_id' => 2]);
        $this->get('/nuevo-oficio/8')->assertOk()->assertSee('12.50');
    }

    public function test_concursante_cannot_edit_catalog_and_admin_cannot_save_a_proposal(): void
    {
        app(PriceCatalogs::class)->prepare(8, 1);
        $this->asUser(2);
        $this->get('/catalogos')->assertForbidden();
        $this->post('/catalogos/8/productos', ['nombre' => 'Nuevo'])->assertForbidden();

        $this->asUser(1);
        $this->post('/nuevo-oficio/8/precios', ['precios' => []])->assertForbidden();
    }

    public function test_document_types_are_imported_once_and_administrator_can_edit_them(): void
    {
        $this->assertSame(0, app(DocumentTypes::class)->importLegacy());
        $this->assertDatabaseCount('ccyf_tipos_documento', 2);
        $this->asUser(1);
        $this->get('/tipos-documento')->assertOk()->assertSee('Convocatoria')->assertSee('Oficio Externo');
        $this->post('/tipos-documento', ['nombre' => 'convocatoria'])->assertSessionHasErrors('nombre');
        $this->put('/tipos-documento/1', ['nombre' => 'Convocatoria'])->assertSessionHasNoErrors();
        $this->post('/tipos-documento', ['nombre' => 'Aviso'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_tipos_documento', ['nombre' => 'Aviso', 'activo' => 1]);
    }

    public function test_inactive_types_are_excluded_and_last_active_type_cannot_be_deactivated(): void
    {
        $catalog = app(PriceCatalogs::class)->prepare(8, 1);
        $prices = DB::table('ccyf_productos')->where('catalogo_id', $catalog)
            ->pluck('id')->mapWithKeys(fn ($id) => [$id => '10.00'])->all();
        $this->asUser(1);
        $this->patch('/tipos-documento/1/estado')->assertSessionHasErrors('tipo');
        $this->patch('/tipos-documento/2/estado')->assertSessionHasNoErrors();
        $this->patch('/tipos-documento/1/estado')->assertSessionHasNoErrors();

        $this->asUser(2);
        $this->get('/nuevo-oficio/8')->assertOk()->assertSee('Oficio Externo')->assertDontSee('<option value="1"', false);
        $this->post('/nuevo-oficio/8/precios', ['tipo_documento_id' => 1, 'precios' => $prices])
            ->assertSessionHasErrors('tipo_documento_id');
        $this->post('/nuevo-oficio/8/precios', ['tipo_documento_id' => 2, 'precios' => $prices])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_precio_borradores', ['tipo_documento_id' => 2, 'usu_id' => 2]);
    }

    public function test_concursante_cannot_manage_document_types(): void
    {
        $this->asUser(2);
        $this->get('/tipos-documento')->assertForbidden();
        $this->post('/tipos-documento', ['nombre' => 'Otro'])->assertForbidden();
        $this->patch('/tipos-documento/1/estado')->assertForbidden();
    }

    public function test_service_types_import_once_and_keep_legacy_usage(): void
    {
        DB::connection('legacy')->table('tm_documento')->insert([
            'num_doc' => '8', 'trami_id' => 3, 'tipo_id' => 1,
        ]);
        $this->assertSame(0, app(ServiceTypes::class)->importLegacy());
        $this->assertDatabaseCount('ccyf_tipos_servicio', 3);

        $this->asUser(1);
        $this->get('/tipos-servicios')->assertOk()
            ->assertSee('Cafetería')->assertSee('Fotocopiado')->assertSee('1 oficios anteriores');
    }

    public function test_administrator_can_manage_services_and_names_update_new_office(): void
    {
        app(PriceCatalogs::class)->prepare(8, 1);
        $this->asUser(1);
        $this->post('/tipos-servicios', [
            'nombre' => 'cafetería', 'descripcion' => 'Duplicado',
        ])->assertSessionHasErrors('nombre');
        $this->post('/tipos-servicios', [
            'nombre' => 'Máquinas expendedoras', 'descripcion' => 'Venta automatizada',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ccyf_tipos_servicio', ['nombre' => 'Máquinas expendedoras', 'activo' => 1]);
        $this->put('/tipos-servicios/1', [
            'nombre' => 'Cafetería escolar', 'descripcion' => 'Servicio de alimentos en plantel',
        ])->assertSessionHasNoErrors();
        $this->get('/nuevo-oficio')->assertOk()->assertSee('Cafetería escolar');
        $this->get('/nuevo-oficio/8')->assertOk()->assertSee('Cafetería escolar');
    }

    public function test_inactive_service_is_not_available_for_new_offices_or_catalogs(): void
    {
        app(PriceCatalogs::class)->prepare(8, 1);
        $this->asUser(1);
        $this->patch('/tipos-servicios/1/estado')->assertSessionHasNoErrors();
        $this->get('/nuevo-oficio')->assertOk()->assertDontSee('Convocatoria de Cafetería');
        $this->get('/nuevo-oficio/8')->assertNotFound();
        $this->get('/catalogos/9')->assertOk()->assertDontSee('<option value="1"', false);
    }

    public function test_last_service_cannot_be_deactivated_and_concursante_cannot_manage_services(): void
    {
        $this->asUser(1);
        $this->patch('/tipos-servicios/1/estado')->assertSessionHasNoErrors();
        $this->patch('/tipos-servicios/2/estado')->assertSessionHasErrors('servicio');

        $this->asUser(2);
        $this->get('/tipos-servicios')->assertForbidden();
        $this->post('/tipos-servicios', ['nombre' => 'Otro', 'descripcion' => 'Otro'])->assertForbidden();
        $this->patch('/tipos-servicios/2/estado')->assertForbidden();
    }

    private function asUser(int $id): void
    {
        $this->be(LegacyUser::query()->findOrFail($id));
    }
}
