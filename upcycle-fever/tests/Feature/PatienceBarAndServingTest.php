<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Game\ServingService;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\RecipeSeeder;
use Database\Seeders\ToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatienceBarAndServingTest extends TestCase
{
    use RefreshDatabase;

    protected User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MaterialSeeder::class);
        $this->seed(ToolSeeder::class);
        $this->seed(RecipeSeeder::class);

        $this->player = User::factory()->create([
            'role'       => 'player',
            'is_active'  => true,
            'coins'      => 100,
            'eco_points' => 50,
            'xp'         => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_workbench_renders_vertical_green_patience_bar_and_feedback_popups(): void
    {
        $response = $this->actingAs($this->player)->get(route('game.workbench'));

        $response->assertStatus(200);
        $response->assertSee('data-testid="patience-bar-vertical"', false);
        $response->assertSee('data-testid="patience-bar-fill"', false);
        $response->assertSee('bg-green-500', false);
        $response->assertSee('height: ${patiencePercent(customer)}%', false);
        $response->assertSee('data-testid="reward-popup"', false);
        $response->assertSee('data-testid="penalty-popup"', false);
    }

    public function test_serving_matching_product_awards_patience_based_speed_bonus(): void
    {
        $now = Carbon::parse('2026-11-18 11:00:00');
        Carbon::setTestNow($now);

        $recipe = Recipe::first();

        // 1) Fast tier (> 50% patience remaining -> +30% coins, +20% eco)
        $fastOrder = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 0,
            'customer_name'      => 'Pak Kumis',
            'customer_avatar'    => '👨',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 60,
            'patience_remaining' => 50,
            'spawned_at'         => $now->copy()->subSeconds(10),
            'expires_at'         => $now->copy()->addSeconds(50),
            'status'             => 'waiting',
        ]);

        $fastRes = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $fastOrder->id,
            'recipe_id' => $recipe->id,
        ]);

        $expectedFastBonusCoins = (int) round($recipe->coin_reward * 0.30);
        $expectedFastBonusEco   = (int) round($recipe->eco_points * 0.20);

        $fastRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('reward.speed_tier', 'fast')
            ->assertJsonPath('reward.bonus_coins', $expectedFastBonusCoins)
            ->assertJsonPath('reward.coins', $recipe->coin_reward + $expectedFastBonusCoins)
            ->assertJsonPath('reward.bonus_eco_points', $expectedFastBonusEco)
            ->assertJsonPath('reward.eco_points', $recipe->eco_points + $expectedFastBonusEco);

        // 2) Normal tier (26%..70% patience remaining -> base reward, no speed bonus)
        $normalOrder = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 1,
            'customer_name'      => 'Ibu Gaul',
            'customer_avatar'    => '👩',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 60,
            'patience_remaining' => 24,
            'spawned_at'         => $now->copy()->subSeconds(36),
            'expires_at'         => $now->copy()->addSeconds(24),
            'status'             => 'waiting',
        ]);

        $normalRes = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $normalOrder->id,
            'recipe_id' => $recipe->id,
        ]);

        $normalRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('speed_bonus', false)
            ->assertJsonPath('reward.speed_tier', 'normal')
            ->assertJsonPath('reward.bonus_coins', 0)
            ->assertJsonPath('reward.bonus_eco_points', 0)
            ->assertJsonPath('reward.coins', $recipe->coin_reward);

        // 3) Urgent tier (<= 25% patience remaining -> base reward, 0 bonus)
        $urgentOrder = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 2,
            'customer_name'      => 'Kak Ceria',
            'customer_avatar'    => '👧',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 60,
            'patience_remaining' => 10,
            'spawned_at'         => $now->copy()->subSeconds(50),
            'expires_at'         => $now->copy()->addSeconds(10),
            'status'             => 'waiting',
        ]);

        $urgentRes = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $urgentOrder->id,
            'recipe_id' => $recipe->id,
        ]);

        $urgentRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('reward.speed_tier', 'urgent')
            ->assertJsonPath('reward.bonus_coins', 0)
            ->assertJsonPath('reward.coins', $recipe->coin_reward);
    }

    public function test_serving_wrong_product_is_rejected_without_altering_order(): void
    {
        $now = Carbon::parse('2026-11-18 11:00:00');
        Carbon::setTestNow($now);

        $recipes = Recipe::take(2)->get();
        $this->assertCount(2, $recipes);

        $order = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 0,
            'customer_name'      => 'Dosen Eco',
            'customer_avatar'    => '🧑‍🏫',
            'recipe_id'          => $recipes[0]->id,
            'patience_max'       => 60,
            'patience_remaining' => 45,
            'spawned_at'         => $now->copy()->subSeconds(15),
            'expires_at'         => $now->copy()->addSeconds(45),
            'status'             => 'waiting',
        ]);

        $res = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $order->id,
            'recipe_id' => $recipes[1]->id,
        ]);

        $res->assertStatus(422)->assertJsonPath('success', false);
        $this->assertDatabaseHas('customer_orders', [
            'id'     => $order->id,
            'status' => 'waiting',
        ]);
        $this->assertEquals(100, $this->player->fresh()->coins);
    }

    public function test_expired_customer_leaves_and_applies_clamped_penalty_without_double_charge(): void
    {
        $now = Carbon::parse('2026-11-18 11:00:00');
        Carbon::setTestNow($now);

        // Set low balance to verify unsigned clamping (>= 0)
        $this->player->update(['coins' => 5, 'eco_points' => 2]);

        $recipe = Recipe::first();
        $order = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 0,
            'customer_name'      => 'Nenek Hijau',
            'customer_avatar'    => '👵',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 45,
            'patience_remaining' => 0,
            'spawned_at'         => $now->copy()->subSeconds(45),
            'expires_at'         => $now,
            'status'             => 'waiting',
        ]);

        $expireRes = $this->actingAs($this->player)->postJson(route('api.game.customers.expire'), [
            'order_id' => $order->id,
        ]);

        $expireRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('penalty.coins', 5)
            ->assertJsonPath('penalty.eco_points', 2)
            ->assertJsonPath('penalty.score', ServingService::EXPIRE_SCORE_PENALTY);

        $this->assertDatabaseHas('customer_orders', [
            'id'     => $order->id,
            'status' => 'expired',
        ]);
        $this->assertEquals(0, $this->player->fresh()->coins);
        $this->assertEquals(0, $this->player->fresh()->eco_points);

        // Duplicate expire call must be rejected and not apply penalty again
        $secondExpire = $this->actingAs($this->player)->postJson(route('api.game.customers.expire'), [
            'order_id' => $order->id,
        ]);
        $secondExpire->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_serving_just_expired_customer_is_rejected_and_applies_penalty_once(): void
    {
        $now = Carbon::parse('2026-11-18 11:00:00');
        Carbon::setTestNow($now);

        $recipe = Recipe::first();
        $order = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 0,
            'customer_name'      => 'Om Kreatif',
            'customer_avatar'    => '🧔',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 45,
            'patience_remaining' => 1,
            'spawned_at'         => $now->copy()->subSeconds(45),
            'expires_at'         => $now->copy()->subSecond(), // Baru saja habis di server
            'status'             => 'waiting',
        ]);

        $res = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $order->id,
            'recipe_id' => $recipe->id,
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('expired', true)
            ->assertJsonPath('penalty.coins', ServingService::EXPIRE_COIN_PENALTY)
            ->assertJsonPath('penalty.eco_points', ServingService::EXPIRE_ECO_PENALTY);

        $this->assertEquals(100 - ServingService::EXPIRE_COIN_PENALTY, $this->player->fresh()->coins);
        $this->assertEquals(50 - ServingService::EXPIRE_ECO_PENALTY, $this->player->fresh()->eco_points);

        // Retry serve on same expired order must not apply penalty a second time
        $retry = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $order->id,
            'recipe_id' => $recipe->id,
        ]);
        $retry->assertStatus(422)->assertJsonPath('success', false);
        $this->assertEquals(100 - ServingService::EXPIRE_COIN_PENALTY, $this->player->fresh()->coins);
    }

    public function test_double_serving_same_order_is_prevented(): void
    {
        $now = Carbon::parse('2026-11-18 11:00:00');
        Carbon::setTestNow($now);

        $recipe = Recipe::first();
        $order = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 0,
            'customer_name'      => 'Adik Pintar',
            'customer_avatar'    => '🧒',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 60,
            'patience_remaining' => 50,
            'spawned_at'         => $now->copy()->subSeconds(10),
            'expires_at'         => $now->copy()->addSeconds(50),
            'status'             => 'waiting',
        ]);

        $first = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $order->id,
            'recipe_id' => $recipe->id,
        ]);
        $first->assertStatus(200)->assertJsonPath('success', true);
        $coinsAfterFirst = $this->player->fresh()->coins;

        $second = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $order->id,
            'recipe_id' => $recipe->id,
        ]);
        $second->assertStatus(422)->assertJsonPath('success', false);
        $this->assertEquals($coinsAfterFirst, $this->player->fresh()->coins);
    }
}
