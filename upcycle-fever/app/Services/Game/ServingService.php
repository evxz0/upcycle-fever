<?php

namespace App\Services\Game;

use App\Models\CustomerOrder;
use App\Models\Recipe;
use App\Models\StageSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ServingService
{
    public const MAX_SLOTS = 3;
    public const SPEED_BONUS_THRESHOLD = 70.0; // > 70% sisa kesabaran (PBI-012 AC 1)
    public const SPEED_BONUS_COIN_RATE = 0.30; // +30% bonus koin
    public const SPEED_BONUS_ECO_RATE = 0.20;  // +20% bonus eco-points
    public const NETWORK_GRACE_SECONDS = 1.0;  // Toleransi latensi jaringan maksimal 1 detik
    public const EXPIRE_COIN_PENALTY = 10;
    public const EXPIRE_ECO_PENALTY = 5;
    public const EXPIRE_SCORE_PENALTY = 20;

    // Persona NPC pelanggan (nama & avatar)
    protected array $customers = [
        ['name' => 'Pak Kumis',     'avatar' => '👨'],
        ['name' => 'Ibu Gaul',     'avatar' => '👩'],
        ['name' => 'Adik Pintar',  'avatar' => '🧒'],
        ['name' => 'Nenek Hijau',  'avatar' => '👵'],
        ['name' => 'Om Kreatif',   'avatar' => '🧔'],
        ['name' => 'Kak Ceria',    'avatar' => '👧'],
        ['name' => 'Dosen Eco',    'avatar' => '🧑‍🏫'],
        ['name' => 'Si Kembar',    'avatar' => '👦'],
    ];

    /**
     * Format satu CustomerOrder menjadi payload array seragam untuk UI/API.
     */
    public function formatOrder(CustomerOrder $o): array
    {
        $o->loadMissing('recipe');
        $remaining = $o->computedPatienceRemaining();
        $percent = $o->patience_max > 0
            ? (int) round(($remaining / $o->patience_max) * 100)
            : 0;

        return [
            'id'                 => $o->id,
            'stage_session_id'   => $o->stage_session_id,
            'slot_index'         => $o->slot_index,
            'customer_name'      => $o->customer_name,
            'customer_avatar'    => $o->customer_avatar,
            'recipe_id'          => $o->recipe_id,
            'recipe_name'        => $o->recipe->name,
            'recipe_icon'        => $o->recipe->output_icon,
            'patience_max'       => $o->patience_max,
            'patience_remaining' => $remaining,
            'patience_percent'   => max(0, min(100, $percent)),
            'spawned_at'         => $o->spawned_at?->toIso8601String(),
            'expires_at'         => $o->expires_at?->toIso8601String(),
            'status'             => $o->status,
        ];
    }

    /**
     * Ambil pelanggan aktif untuk satu sesi stage (tanpa memaksa isi penuh 3 slot sekaligus).
     */
    public function getStageCustomers(StageSession $stage): array
    {
        return CustomerOrder::with('recipe')
            ->where('stage_session_id', $stage->id)
            ->where('status', 'waiting')
            ->orderBy('slot_index')
            ->get()
            ->map(fn($o) => $this->formatOrder($o))
            ->values()
            ->toArray();
    }

    /**
     * Ambil/isi 3 pelanggan aktif (waiting) di slot meja kasir (kompatibilitas PBI-006).
     */
    public function getActiveCustomers(?int $userId): array
    {
        $active = CustomerOrder::with('recipe')
            ->where('user_id', $userId)
            ->where('status', 'waiting')
            ->orderBy('slot_index')
            ->get();

        // Isi slot kosong sampai MAX_SLOTS
        $occupiedSlots = $active->pluck('slot_index')->toArray();
        for ($i = 0; $i < self::MAX_SLOTS; $i++) {
            if (!in_array($i, $occupiedSlots)) {
                $order = $this->spawnCustomer($userId, $i);
                if ($order) $active->push($order);
            }
        }

        return $active->sortBy('slot_index')
            ->values()
            ->map(fn($o) => $this->formatOrder($o))
            ->toArray();
    }

    /**
     * Spawn pelanggan baru acak di slot tertentu dengan skema timer kesabaran.
     */
    public function spawnCustomer(?int $userId, int $slotIndex, ?int $stageSessionId = null): ?CustomerOrder
    {
        $recipe = Recipe::inRandomOrder()->first();
        if (!$recipe) return null;

        $persona = $this->customers[array_rand($this->customers)];
        $patience = rand(45, 75);
        $now = now();

        return CustomerOrder::create([
            'user_id'            => $userId,
            'stage_session_id'   => $stageSessionId,
            'slot_index'         => $slotIndex,
            'customer_name'      => $persona['name'],
            'customer_avatar'    => $persona['avatar'],
            'recipe_id'          => $recipe->id,
            'patience_max'       => $patience,
            'patience_remaining' => $patience,
            'spawned_at'         => $now,
            'expires_at'         => $now->copy()->addSeconds($patience),
            'status'             => 'waiting',
        ]);
    }

    /**
     * Hitung imbalan koin, eco-points, dan skor berdasarkan sisa kesabaran pelanggan (PBI-011 & PBI-012).
     * Speed Bonus diberikan bila sisa kesabaran > 70% dari patience_max.
     */
    public function calculatePatienceReward(Recipe $recipe, float $patienceRemaining, int $patienceMax): array
    {
        $ratio   = $patienceMax > 0 ? max(0.0, min(1.0, $patienceRemaining / $patienceMax)) : 0.0;
        $percent = $ratio * 100.0;

        $baseCoins = $recipe->coin_reward;
        $baseEco   = $recipe->eco_points;
        $baseScore = $baseCoins + ($baseEco * 2);

        $isSpeedBonus = $percent > self::SPEED_BONUS_THRESHOLD;

        if ($isSpeedBonus) {
            $bonusCoins = (int) round($baseCoins * self::SPEED_BONUS_COIN_RATE);
            $bonusEco   = (int) round($baseEco * self::SPEED_BONUS_ECO_RATE);
            $speedTier  = 'fast';
        } else {
            $bonusCoins = 0;
            $bonusEco   = 0;
            $speedTier  = $percent > 25.0 ? 'normal' : 'urgent';
        }

        $totalCoins = $baseCoins + $bonusCoins;
        $totalEco   = $baseEco + $bonusEco;
        $bonusScore = $bonusCoins + ($bonusEco * 2);
        $totalScore = $baseScore + $bonusScore;

        return [
            'base_coins'          => $baseCoins,
            'bonus_coins'         => $bonusCoins,
            'coins'               => $totalCoins,
            'base_eco_points'     => $baseEco,
            'bonus_eco_points'    => $bonusEco,
            'eco_points'          => $totalEco,
            'base_score'          => $baseScore,
            'bonus_score'         => $bonusScore,
            'score'               => $totalScore,
            'xp'                  => 10,
            'patience_ratio'      => round($ratio, 4),
            'patience_percent'    => round($percent, 2),
            'speed_bonus_awarded' => $isSpeedBonus,
            'speed_bonus_label'   => $isSpeedBonus ? '+Speed Bonus!' : null,
            'speed_tier'          => $speedTier,
        ];
    }

    /**
     * Terapkan penalti koin & eco-points secara aman (clamped >= 0 untuk kolom unsigned).
     */
    public function applyExpirePenalty(?int $userId, int $expiredCount = 1): array
    {
        $coinPenalty  = self::EXPIRE_COIN_PENALTY * $expiredCount;
        $ecoPenalty   = self::EXPIRE_ECO_PENALTY * $expiredCount;
        $scorePenalty = self::EXPIRE_SCORE_PENALTY * $expiredCount;

        $deductedCoins = $coinPenalty;
        $deductedEco   = $ecoPenalty;

        if ($userId && $expiredCount > 0) {
            $user = User::where('id', $userId)->lockForUpdate()->first();
            if ($user) {
                $deductedCoins = min($user->coins, $coinPenalty);
                $deductedEco   = min($user->eco_points, $ecoPenalty);
                $user->update([
                    'coins'      => max(0, $user->coins - $coinPenalty),
                    'eco_points' => max(0, $user->eco_points - $ecoPenalty),
                ]);
            }
        }

        return [
            'coins'      => $deductedCoins,
            'eco_points' => $deductedEco,
            'score'      => $scorePenalty,
        ];
    }

    /**
     * Serahkan produk ke pelanggan (PBI-006, PBI-011 & PBI-012: atomik, speed bonus >70%, auto-expire + penalti).
     */
    public function serveProduct(?int $userId, int $orderId, int $recipeId, ?float $clientPatienceRemaining = null): array
    {
        return DB::transaction(function () use ($userId, $orderId, $recipeId, $clientPatienceRemaining) {
            $order = CustomerOrder::with(['recipe', 'stageSession'])
                ->where('id', $orderId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$order || $order->status !== 'waiting') {
                return [
                    'success' => false,
                    'message' => 'Pesanan tidak ditemukan, sudah dilayani, atau sudah kedaluwarsa.',
                ];
            }

            // Validasi sesi stage masih aktif
            $stage = $order->stageSession;
            if ($stage && ($stage->status !== 'active' || $stage->remainingSeconds() <= 0)) {
                return [
                    'success' => false,
                    'message' => 'Waktu sesi stage sudah habis! Pesanan tidak dapat diserahkan.',
                ];
            }

            // Edge case: pelanggan baru saja kehabisan kesabaran tepat saat diserahkan (basis waktu server)
            $serverPatienceFloat = $order->computedPatienceSecondsFloat();
            if ($serverPatienceFloat <= 0.0) {
                $order->update(['status' => 'expired', 'patience_remaining' => 0]);
                $penalty = $this->applyExpirePenalty($userId);

                $newOrder = null;
                if ($stage) {
                    $stage->increment('orders_expired');
                    $remainingWaiting = CustomerOrder::where('stage_session_id', $stage->id)
                        ->where('status', 'waiting')
                        ->count();
                    if ($remainingWaiting === 0) {
                        $newOrder = $this->spawnCustomer($userId, $order->slot_index, $stage->id);
                    }
                } else {
                    $newOrder = $this->spawnCustomer($userId, $order->slot_index);
                }

                return [
                    'success'      => false,
                    'expired'      => true,
                    'message'      => "Terlambat! {$order->customer_name} sudah pergi karena kesabaran habis (-{$penalty['coins']} Koin, -{$penalty['eco_points']} Eco, -{$penalty['score']} Skor).",
                    'penalty'      => $penalty,
                    'new_customer' => $newOrder ? $this->formatOrder($newOrder) : null,
                ];
            }

            // Validasi kecocokan spesifikasi produk jadi dengan pesanan pelanggan
            if ($order->recipe_id !== $recipeId) {
                return [
                    'success' => false,
                    'message' => 'Produk yang diserahkan tidak sesuai pesanan pelanggan ini!',
                ];
            }

            // Toleransi latensi jaringan (maks 1 detik) agar selisih milidetik di batas 70% tidak merugikan pemain
            $effectivePatience = $serverPatienceFloat;
            if ($clientPatienceRemaining !== null && $clientPatienceRemaining > $serverPatienceFloat) {
                $effectivePatience = min(
                    (float) $order->patience_max,
                    $clientPatienceRemaining,
                    $serverPatienceFloat + self::NETWORK_GRACE_SECONDS
                );
            }

            // Tandai pesanan selesai & hitung imbalan termasuk Speed Bonus (>70%)
            $reward = $this->calculatePatienceReward($order->recipe, $effectivePatience, $order->patience_max);

            $order->update([
                'status'             => 'completed',
                'patience_remaining' => (int) ceil($serverPatienceFloat),
            ]);

            if ($userId) {
                $user = User::where('id', $userId)->lockForUpdate()->first();
                if ($user) {
                    $user->increment('coins', $reward['coins']);
                    $user->increment('eco_points', $reward['eco_points']);
                    $user->increment('xp', $reward['xp']);
                }
            }

            if ($stage) {
                $stage->increment('orders_completed');
                $stage->increment('coins_earned', $reward['coins']);
                $stage->increment('eco_points_earned', $reward['eco_points']);
                $stage->increment('xp_earned', $reward['xp']);
            }

            // Pada sesi stage (PBI-010), hanya spawn langsung jika antrean kosong
            $newOrder = null;
            if ($stage) {
                $remainingWaiting = CustomerOrder::where('stage_session_id', $stage->id)
                    ->where('status', 'waiting')
                    ->count();
                if ($remainingWaiting === 0) {
                    $newOrder = $this->spawnCustomer($userId, $order->slot_index, $stage->id);
                }
            } else {
                $newOrder = $this->spawnCustomer($userId, $order->slot_index);
            }

            $bonusLabel = $reward['speed_bonus_awarded']
                ? " +Speed Bonus! (+{$reward['bonus_coins']} Koin, +{$reward['bonus_score']} Skor)"
                : '';

            return [
                'success'      => true,
                'speed_bonus'  => $reward['speed_bonus_awarded'],
                'message'      => "Pesanan {$order->recipe->name} berhasil diserahkan ke {$order->customer_name}!{$bonusLabel}",
                'reward'       => $reward,
                'new_customer' => $newOrder ? $this->formatOrder($newOrder) : null,
            ];
        });
    }

    /**
     * Expire pelanggan yang kehabisan kesabaran & berikan penalti (PBI-011 AC 2, atomik anti double-penalty).
     */
    public function expireCustomer(?int $userId, int $orderId): array
    {
        return DB::transaction(function () use ($userId, $orderId) {
            $order = CustomerOrder::with('stageSession')
                ->where('id', $orderId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$order || $order->status !== 'waiting') {
                return ['success' => false, 'message' => 'Pesanan tidak ditemukan atau sudah diproses.'];
            }

            $order->update(['status' => 'expired', 'patience_remaining' => 0]);
            $penalty = $this->applyExpirePenalty($userId);

            $stage = $order->stageSession;
            $newOrder = null;
            if ($stage) {
                $stage->increment('orders_expired');
                if ($stage->status === 'active' && $stage->remainingSeconds() > 0) {
                    $remainingWaiting = CustomerOrder::where('stage_session_id', $stage->id)
                        ->where('status', 'waiting')
                        ->count();
                    if ($remainingWaiting === 0) {
                        $newOrder = $this->spawnCustomer($userId, $order->slot_index, $stage->id);
                    }
                }
            } else {
                $newOrder = $this->spawnCustomer($userId, $order->slot_index);
            }

            return [
                'success'      => true,
                'message'      => "{$order->customer_name} pergi karena kesabaran habis! Penalti: -{$penalty['coins']} Koin, -{$penalty['eco_points']} Eco, -{$penalty['score']} Skor.",
                'penalty'      => $penalty,
                'new_customer' => $newOrder ? $this->formatOrder($newOrder) : null,
            ];
        });
    }
}

