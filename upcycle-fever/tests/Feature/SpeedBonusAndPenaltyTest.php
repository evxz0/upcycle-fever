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

class SpeedBonusAndPenaltyTest extends TestCase
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

    public function test_workbench_renders_speed_bonus_floating_effect_and_penalty_score_indicator(): void
    {
        $response = $this->actingAs($this->player)->get(route('game.workbench'));

        $response->assertStatus(200);
        $response->assertSee('data-testid="speed-bonus-floating"', false);
        $response->assertSee('+Speed Bonus!');
        $response->assertSee('animate-speed-bonus', false);
        $response->assertSee('data-testid="penalty-popup"', false);
        $response->assertSee('-${penaltyPopup.score} Skor', false);
    }

    public function test_speed_bonus_awarded_when_patience_strictly_above_70_percent(): void
    {
        $now = Carbon::parse('2026-11-18 12:00:00');
        Carbon::setTestNow($now);

        $recipe = Recipe::first();
        // 45s / 60s = 75% (> 70%)
        $order = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 0,
            'customer_name'      => 'Pak Kumis',
            'customer_avatar'    => '👨',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 60,
            'patience_remaining' => 45,
            'spawned_at'         => $now->copy()->subSeconds(15),
            'expires_at'         => $now->copy()->addSeconds(45),
            'status'             => 'waiting',
        ]);

        $res = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $order->id,
            'recipe_id' => $recipe->id,
        ]);

        $expectedBonusCoins = (int) round($recipe->coin_reward * ServingService::SPEED_BONUS_COIN_RATE);
        $expectedBonusEco   = (int) round($recipe->eco_points * ServingService::SPEED_BONUS_ECO_RATE);
        $expectedBonusScore = $expectedBonusCoins + ($expectedBonusEco * 2);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('speed_bonus', true)
            ->assertJsonPath('reward.speed_bonus_awarded', true)
            ->assertJsonPath('reward.speed_bonus_label', '+Speed Bonus!')
            ->assertJsonPath('reward.base_coins', $recipe->coin_reward)
            ->assertJsonPath('reward.bonus_coins', $expectedBonusCoins)
            ->assertJsonPath('reward.coins', $recipe->coin_reward + $expectedBonusCoins)
            ->assertJsonPath('reward.bonus_eco_points', $expectedBonusEco)
            ->assertJsonPath('reward.bonus_score', $expectedBonusScore)
            ->assertJsonPath('reward.patience_percent', 75);

        $this->assertEquals(100 + $recipe->coin_reward + $expectedBonusCoins, $this->player->fresh()->coins);
    }

    public function test_no_speed_bonus_when_patience_is_exactly_70_percent_or_lower(): void
    {
        $now = Carbon::parse('2026-11-18 12:00:00');
        Carbon::setTestNow($now);

        $recipe = Recipe::first();
        // 42s / 60s = tepat 70.0% (tidak > 70%)
        $order = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 0,
            'customer_name'      => 'Ibu Gaul',
            'customer_avatar'    => '👩',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 60,
            'patience_remaining' => 42,
            'spawned_at'         => $now->copy()->subSeconds(18),
            'expires_at'         => $now->copy()->addSeconds(42),
            'status'             => 'waiting',
        ]);

        $res = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $order->id,
            'recipe_id' => $recipe->id,
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('speed_bonus', false)
            ->assertJsonPath('reward.speed_bonus_awarded', false)
            ->assertJsonPath('reward.speed_bonus_label', null)
            ->assertJsonPath('reward.bonus_coins', 0)
            ->assertJsonPath('reward.bonus_score', 0)
            ->assertJsonPath('reward.coins', $recipe->coin_reward);
    }

    public function test_millisecond_precision_and_network_grace_protect_player_at_70_percent_boundary(): void
    {
        $recipe = Recipe::first();

        // Kasus A: expires_at tersimpan pada 12:00:43 (presisi detik DB), request masuk pada 12:00:00.700
        // Sisa aktual = 42.3 detik dari 60 detik (70.5% > 70%) — tidak boleh terpotong jadi 42s (70%)
        Carbon::setTestNow(Carbon::parse('2026-11-18 12:00:00.700'));
        $msOrder = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 0,
            'customer_name'      => 'Kak Ceria',
            'customer_avatar'    => '👧',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 60,
            'patience_remaining' => 43,
            'spawned_at'         => Carbon::parse('2026-11-18 11:59:43'),
            'expires_at'         => Carbon::parse('2026-11-18 12:00:43'),
            'status'             => 'waiting',
        ]);

        $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $msOrder->id,
            'recipe_id' => $recipe->id,
        ])->assertStatus(200)
            ->assertJsonPath('speed_bonus', true)
            ->assertJsonPath('reward.speed_bonus_awarded', true);

        $now = Carbon::parse('2026-11-18 12:00:00.000');
        Carbon::setTestNow($now);

        // Kasus B: Latensi jaringan — pemain klik saat 43s (71.67%), sampai server 42.0s (70.0%), masuk toleransi 1s
        $graceOrder = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 1,
            'customer_name'      => 'Dosen Eco',
            'customer_avatar'    => '🧑‍🏫',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 60,
            'patience_remaining' => 42,
            'spawned_at'         => $now->copy()->subSeconds(18),
            'expires_at'         => $now->copy()->addSeconds(42),
            'status'             => 'waiting',
        ]);

        $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'                  => $graceOrder->id,
            'recipe_id'                 => $recipe->id,
            'client_patience_remaining' => 43,
        ])->assertStatus(200)
            ->assertJsonPath('speed_bonus', true);

        // Kasus C: Anti-spoofing — server 35s (58.3%), klien coba klaim 60s -> dibatasi +1s (36s = 60% <= 70%) -> ditolak bonusnya
        $spoofOrder = CustomerOrder::create([
            'user_id'            => $this->player->id,
            'slot_index'         => 2,
            'customer_name'      => 'Si Kembar',
            'customer_avatar'    => '👦',
            'recipe_id'          => $recipe->id,
            'patience_max'       => 60,
            'patience_remaining' => 35,
            'spawned_at'         => $now->copy()->subSeconds(25),
            'expires_at'         => $now->copy()->addSeconds(35),
            'status'             => 'waiting',
        ]);

        $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'                  => $spoofOrder->id,
            'recipe_id'                 => $recipe->id,
            'client_patience_remaining' => 60,
        ])->assertStatus(200)
            ->assertJsonPath('speed_bonus', false)
            ->assertJsonPath('reward.bonus_coins', 0);
    }

    public function test_speed_bonus_and_expire_penalty_reconcile_consistently_in_stage_recap(): void
    {
        $startTime = Carbon::parse('2026-11-18 12:00:00');
        Carbon::setTestNow($startTime);

        $startRes = $this->actingAs($this->player)->postJson(route('api.game.stage.start'), [
            'stage_number'           => 1,
            'duration_seconds'       => 120,
            'spawn_interval_seconds' => 10,
        ]);
        $stageId = $startRes->json('stage.id');

        // Spawn pelanggan kedua setelah 10 detik
        Carbon::setTestNow($startTime->copy()->addSeconds(10));
        $this->actingAs($this->player)->postJson(route('api.game.stage.spawn'), [
            'stage_session_id' => $stageId,
        ])->assertJsonPath('spawned', true);

        $orders = CustomerOrder::with('recipe')
            ->where('stage_session_id', $stageId)
            ->where('status', 'waiting')
            ->orderBy('slot_index')
            ->get();

        // Layani pelanggan ke-2 langsung (sisa patience 100% > 70% -> wajib dapat Speed Bonus!)
        $fastOrder = $orders[1];
        $serveRes = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $fastOrder->id,
            'recipe_id' => $fastOrder->recipe_id,
        ]);

        $serveRes->assertStatus(200)
            ->assertJsonPath('speed_bonus', true)
            ->assertJsonPath('reward.speed_bonus_awarded', true);

        $rewardScore = $serveRes->json('reward.score');
        $this->assertGreaterThan($serveRes->json('reward.base_score'), $rewardScore);

        // Expire pelanggan pertama -> penalti skor & koin
        $expiredOrder = $orders[0];
        $expireRes = $this->actingAs($this->player)->postJson(route('api.game.customers.expire'), [
            'order_id' => $expiredOrder->id,
        ]);
        $expireRes->assertStatus(200)
            ->assertJsonPath('penalty.score', ServingService::EXPIRE_SCORE_PENALTY);

        // Finish stage -> total_score = rewardScore - EXPIRE_SCORE_PENALTY
        $finishRes = $this->actingAs($this->player)->postJson(route('api.game.stage.finish'), [
            'stage_session_id' => $stageId,
        ]);

        $finishRes->assertStatus(200)
            ->assertJsonPath('recap.orders_completed', 1)
            ->assertJsonPath('recap.orders_expired', 1)
            ->assertJsonPath('recap.penalty_score', ServingService::EXPIRE_SCORE_PENALTY)
            ->assertJsonPath('recap.total_score', max(0, $rewardScore - ServingService::EXPIRE_SCORE_PENALTY));
    }
}
