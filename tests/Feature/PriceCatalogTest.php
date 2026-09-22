<?php

namespace Tests\Feature;

use App\Models\LegacyUser;
use App\Services\PriceCatalogs;
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
            ['men_id' => 16, 'men_nom' => 'Categorias_widi', 'est' => 1],
        ]);
        DB::connection('legacy')->table('td_medu_detalle')->insert([
            ['rol_id' => 18, 'men_id' => 16, 'mend_permi' => 'si'],
            ['rol_id' => 9, 'men_id' => 4, 'mend_permi' => 'si'],
        ]);
    }

    public function test_admin_can_change_one_convocation_without_affecting_another(): void
    {
        $this->asUser(1);
        $this->post('/catalogos/8', ['tipo' => 'cafeteria'])->assertRedirect('/catalogos/8');
        $this->post('/catalogos/9', ['tipo' => 'fotocopiado'])->assertRedirect('/catalogos/9');
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
        $catalog = app(PriceCatalogs::class)->prepare(8, 'cafeteria');
        $products = DB::table('ccyf_productos')->where('catalogo_id', $catalog)->pluck('id');
        $prices = $products->mapWithKeys(fn ($id) => [$id => '12.50'])->all();
        $this->asUser(2);

        $this->post('/nuevo-oficio/8/precios', ['precios' => $prices + [99999 => '0.01']])
            ->assertSessionHasErrors('precios');
        $this->assertDatabaseCount('ccyf_precio_borradores', 0);

        $this->post('/nuevo-oficio/8/precios', ['precios' => $prices])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ccyf_precio_borrador_detalles', $products->count());
        $this->get('/nuevo-oficio/8')->assertOk()->assertSee('12.50');
    }

    public function test_concursante_cannot_edit_catalog_and_admin_cannot_save_a_proposal(): void
    {
        app(PriceCatalogs::class)->prepare(8, 'cafeteria');
        $this->asUser(2);
        $this->get('/catalogos')->assertForbidden();
        $this->post('/catalogos/8/productos', ['nombre' => 'Nuevo'])->assertForbidden();

        $this->asUser(1);
        $this->post('/nuevo-oficio/8/precios', ['precios' => []])->assertForbidden();
    }

    private function asUser(int $id): void
    {
        $this->be(LegacyUser::query()->findOrFail($id));
    }
}
