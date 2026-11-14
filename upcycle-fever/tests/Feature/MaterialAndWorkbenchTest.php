<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use App\Models\Inventory;
use App\Services\Game\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialAndWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed materials
        $this->seed(\Database\Seeders\MaterialSeeder::class);
    }

    public function test_can_view_materials_catalog_page(): void
    {
        $response = $this->get(route('materials.index'));
        $response->assertStatus(200);
        $response->assertSee('Katalog Master Data Bahan Limbah');
        $response->assertSee('Botol Plastik PET');
    }

    public function test_can_fetch_materials_via_api(): void
    {
        $response = $this->getJson(route('api.materials.index'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'count',
            'data' => [
                '*' => ['id', 'name', 'slug', 'category', 'type', 'icon', 'eco_impact', 'description']
            ]
        ]);
        $this->assertGreaterThanOrEqual(8, $response->json('count'));
    }

    public function test_can_filter_materials_by_category(): void
    {
        $response = $this->getJson(route('api.materials.index', ['category' => 'Plastik']));
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('Plastik', $item['category']);
        }
    }

    public function test_can_search_materials_by_keyword(): void
    {
        $response = $this->getJson(route('api.materials.index', ['search' => 'Aluminium']));
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Kaleng Minuman Aluminium', $data[0]['name']);
    }

    public function test_can_create_new_waste_material_via_api(): void
    {
        $response = $this->postJson(route('api.materials.store'), [
            'name'        => 'Tutup Galon Biru',
            'category'    => 'Plastik',
            'type'        => 'Anorganik',
            'description' => 'Plastik LDPE/HDPE yang dapat dicetak kembali.',
            'icon'        => '🔵',
            'eco_impact'  => 12,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data' => [
                'name'       => 'Tutup Galon Biru',
                'category'   => 'Plastik',
                'eco_impact' => 12,
            ]
        ]);

        $this->assertDatabaseHas('materials', [
            'name'     => 'Tutup Galon Biru',
            'category' => 'Plastik',
        ]);
    }

    public function test_material_creation_validation_fails_on_missing_fields(): void
    {
        $response = $this->postJson(route('api.materials.store'), [
            'description' => 'Tanpa nama dan kategori',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'category', 'eco_impact']);
    }

    public function test_player_can_access_workbench_page(): void
    {
        $player = User::factory()->create([
            'role'      => 'player',
            'is_active' => true,
        ]);

        $response = $this->actingAs($player)->get(route('game.workbench'));
        $response->assertStatus(200);
        $response->assertSee('Meja Kerja Perakitan');
        $response->assertSee('Workbench Slots');
        $response->assertSee('Inventaris Bahan Sampah Pemain');
    }

    public function test_player_receives_starter_inventory_and_can_fetch_it(): void
    {
        $player = User::factory()->create([
            'role'      => 'player',
            'is_active' => true,
        ]);

        $response = $this->actingAs($player)->getJson(route('api.inventory.index'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'count',
            'data' => [
                '*' => ['id', 'user_id', 'material_id', 'quantity', 'material']
            ]
        ]);
        $this->assertGreaterThan(0, $response->json('count'));
    }

    public function test_inventory_service_moves_and_deducts_material(): void
    {
        $player = User::factory()->create(['role' => 'player']);
        $material = Material::first();

        $service = new InventoryService();
        $inv = $service->addToInventory($player->id, $material->id, 5);
        $this->assertEquals(5, $inv->quantity);

        // Deduct 2 for workbench placement
        $deducted = $service->deductFromInventory($player->id, $material->id, 2);
        $this->assertTrue($deducted);

        $freshInv = Inventory::where('user_id', $player->id)->where('material_id', $material->id)->first();
        $this->assertEquals(3, $freshInv->quantity);
    }

    public function test_workbench_validation_returns_preview_and_eco_impact(): void
    {
        $materials = Material::take(2)->get();
        $materialIds = $materials->pluck('id')->toArray();

        $response = $this->postJson(route('api.workbench.validate'), [
            'material_ids' => $materialIds,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'          => true,
            'valid'            => true,
            'count'            => 2,
            'total_eco_impact' => $materials->sum('eco_impact'),
        ]);
    }

    public function test_workbench_validation_fails_when_empty(): void
    {
        $response = $this->postJson(route('api.workbench.validate'), [
            'material_ids' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['material_ids']);
    }
}

