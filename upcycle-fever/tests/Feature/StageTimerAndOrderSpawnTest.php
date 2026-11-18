<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\StageSession;
use App\Models\User;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\RecipeSeeder;
use Database\Seeders\ToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StageTimerAndOrderSpawnTest extends TestCase
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
            'role'      => 'player',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_orders_and_stage_sessions_schema_has_timer_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('stage_sessions', [
            'user_id',
            'stage_number',
            'duration_seconds',
            'spawn_interval_seconds',
            'started_at',
            'ends_at',
            'ended_at',
            'status',
            'orders_completed',
            'orders_expired',
            'coins_earned',
            'eco_points_earned',
            'xp_earned',
        ]));

        $this->assertTrue(Schema::hasColumns('customer_orders', [
            'stage_session_id',
            'patience_max',
            'patience_remaining',
            'spawned_at',
            'expires_at',
            'status',
        ]));
    }

    public function test_workbench_renders_stage_countdown_timer_customer_queue_and_score_recap(): void
    {
        $response = $this->actingAs($this->player)->get(route('game.workbench'));

        $response->assertStatus(200);
        $response->assertSee('data-testid="stage-countdown-timer"', false);
        $response->assertSee('Antrian Pelanggan Studio');
        $response->assertSee('data-testid="stage-score-recap"', false);
        $response->assertSee('Rekapitulasi Skor');

        $stage = StageSession::where('user_id', $this->player->id)->first();
        $this->assertNotNull($stage);
        $this->assertEquals('active', $stage->status);
        $this->assertEquals(1, CustomerOrder::where('stage_session_id', $stage->id)->where('status', 'waiting')->count());
    }

    public function test_periodic_order_spawn_respects_interval_and_max_slot_limit(): void
    {
        $startTime = Carbon::parse('2026-11-18 10:00:00');
        Carbon::setTestNow($startTime);

        // Mulai stage dengan durasi 120s dan laju spawn tiap 10s
        $startRes = $this->actingAs($this->player)->postJson(route('api.game.stage.start'), [
            'stage_number'           => 1,
            'duration_seconds'       => 120,
            'spawn_interval_seconds' => 10,
        ]);

        $startRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage.remaining_seconds', 120)
            ->assertJsonCount(1, 'customers');

        $stageId = $startRes->json('stage.id');

        // 5 detik berlalu (< 10s): tidak boleh spawn pesanan baru
        Carbon::setTestNow($startTime->copy()->addSeconds(5));
        $tooEarly = $this->actingAs($this->player)->postJson(route('api.game.stage.spawn'), [
            'stage_session_id' => $stageId,
        ]);

        $tooEarly->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('spawned', false)
            ->assertJsonPath('next_spawn_in', 5)
            ->assertJsonPath('remaining_seconds', 115)
            ->assertJsonCount(1, 'customers');

        // 10 detik berlalu (>= 10s): spawn pelanggan ke-2 di slot 1
        Carbon::setTestNow($startTime->copy()->addSeconds(10));
        $spawn2 = $this->actingAs($this->player)->postJson(route('api.game.stage.spawn'), [
            'stage_session_id' => $stageId,
        ]);

        $spawn2->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('spawned', true)
            ->assertJsonPath('order.slot_index', 1)
            ->assertJsonCount(2, 'customers');

        // 20 detik berlalu (+10s lagi): spawn pelanggan ke-3 di slot 2
        Carbon::setTestNow($startTime->copy()->addSeconds(20));
        $spawn3 = $this->actingAs($this->player)->postJson(route('api.game.stage.spawn'), [
            'stage_session_id' => $stageId,
        ]);

        $spawn3->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('spawned', true)
            ->assertJsonPath('order.slot_index', 2)
            ->assertJsonCount(3, 'customers');

        // 30 detik berlalu (antrean sudah penuh 3/3): tidak boleh melebihi 3 slot
        Carbon::setTestNow($startTime->copy()->addSeconds(30));
        $fullQueue = $this->actingAs($this->player)->postJson(route('api.game.stage.spawn'), [
            'stage_session_id' => $stageId,
        ]);

        $fullQueue->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('spawned', false)
            ->assertJsonCount(3, 'customers');
    }

    public function test_serving_and_expiring_orders_track_stage_recap_metrics(): void
    {
        $startTime = Carbon::parse('2026-11-18 10:00:00');
        Carbon::setTestNow($startTime);

        $startRes = $this->actingAs($this->player)->postJson(route('api.game.stage.start'), [
            'stage_number'           => 1,
            'duration_seconds'       => 90,
            'spawn_interval_seconds' => 10,
        ]);
        $stageId = $startRes->json('stage.id');

        // Spawn pesanan ke-2 setelah 10 detik
        Carbon::setTestNow($startTime->copy()->addSeconds(10));
        $this->actingAs($this->player)->postJson(route('api.game.stage.spawn'), [
            'stage_session_id' => $stageId,
        ])->assertJsonPath('spawned', true);

        $orders = CustomerOrder::with('recipe')
            ->where('stage_session_id', $stageId)
            ->where('status', 'waiting')
            ->orderBy('slot_index')
            ->get();

        $this->assertCount(2, $orders);

        // Sajikan pesanan pertama
        $firstOrder = $orders[0];
        $serveRes = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $firstOrder->id,
            'recipe_id' => $firstOrder->recipe_id,
        ]);
        $serveRes->assertStatus(200)->assertJsonPath('success', true);

        // Expire pesanan kedua
        $secondOrder = $orders[1];
        $expireRes = $this->actingAs($this->player)->postJson(route('api.game.customers.expire'), [
            'order_id' => $secondOrder->id,
        ]);
        $expireRes->assertStatus(200)->assertJsonPath('success', true);

        // Akhiri stage & cek rekapitulasi skor (termasuk bonus kesabaran & penalti expired)
        $finishRes = $this->actingAs($this->player)->postJson(route('api.game.stage.finish'), [
            'stage_session_id' => $stageId,
        ]);

        $expectedCoins   = $serveRes->json('reward.coins');
        $expectedEco     = $serveRes->json('reward.eco_points');
        $expectedPenalty = \App\Services\Game\ServingService::EXPIRE_SCORE_PENALTY;
        $expectedScore   = max(0, $expectedCoins + ($expectedEco * 2) - $expectedPenalty);

        $finishRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('recap.orders_completed', 1)
            ->assertJsonPath('recap.orders_expired', 1)
            ->assertJsonPath('recap.coins_earned', $expectedCoins)
            ->assertJsonPath('recap.eco_points_earned', $expectedEco)
            ->assertJsonPath('recap.xp_earned', 10)
            ->assertJsonPath('recap.penalty_score', $expectedPenalty)
            ->assertJsonPath('recap.total_score', $expectedScore)
            ->assertJsonPath('recap.stars', 1);
    }

    public function test_stage_finishes_automatically_when_timer_reaches_zero_and_blocks_further_actions(): void
    {
        $startTime = Carbon::parse('2026-11-18 10:00:00');
        Carbon::setTestNow($startTime);

        $startRes = $this->actingAs($this->player)->postJson(route('api.game.stage.start'), [
            'stage_number'           => 1,
            'duration_seconds'       => 30,
            'spawn_interval_seconds' => 10,
        ]);
        $stageId = $startRes->json('stage.id');
        $order   = CustomerOrder::where('stage_session_id', $stageId)->first();

        // Maju ke detik ke-30 (timer stage mencapai 0)
        Carbon::setTestNow($startTime->copy()->addSeconds(30));

        // Upaya spawn setelah timer 0 otomatis mengakhiri sesi & mengembalikan rekapitulasi skor
        $spawnAfterEnd = $this->actingAs($this->player)->postJson(route('api.game.stage.spawn'), [
            'stage_session_id' => $stageId,
        ]);

        $spawnAfterEnd->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('stage_ended', true)
            ->assertJsonStructure([
                'recap' => [
                    'stage_session_id',
                    'stage_number',
                    'orders_completed',
                    'orders_expired',
                    'coins_earned',
                    'eco_points_earned',
                    'total_score',
                    'stars',
                ],
            ]);

        $this->assertDatabaseHas('stage_sessions', [
            'id'     => $stageId,
            'status' => 'completed',
        ]);

        // Upaya menyajikan pesanan setelah sesi berakhir ditolak
        $serveAfterEnd = $this->actingAs($this->player)->postJson(route('api.game.serve'), [
            'order_id'  => $order->id,
            'recipe_id' => $order->recipe_id,
        ]);
        $serveAfterEnd->assertStatus(422)->assertJsonPath('success', false);
    }
}
