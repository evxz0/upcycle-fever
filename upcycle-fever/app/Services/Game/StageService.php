<?php

namespace App\Services\Game;

use App\Models\CustomerOrder;
use App\Models\StageSession;
use App\Models\User;

class StageService
{
    public const DEFAULT_DURATION = 120;
    public const DEFAULT_SPAWN_INTERVAL = 15;

    public function __construct(
        protected ServingService $servingService
    ) {}

    /**
     * Ambil sesi stage yang sedang aktif untuk user, atau mulai sesi baru jika tidak ada / sudah habis.
     */
    public function getOrStartStage(
        ?int $userId,
        int $stageNumber = 1,
        int $duration = self::DEFAULT_DURATION,
        int $spawnInterval = self::DEFAULT_SPAWN_INTERVAL
    ): StageSession {
        $existing = StageSession::where('user_id', $userId)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($existing) {
            if ($existing->remainingSeconds() > 0) {
                $this->syncExpiredOrders($existing);
                if (CustomerOrder::where('stage_session_id', $existing->id)->where('status', 'waiting')->count() === 0) {
                    $this->servingService->spawnCustomer($userId, 0, $existing->id, $existing->stage_number);
                }

                return $existing->fresh();
            }

            $this->finishStage($userId, $existing->id);
        }

        return $this->startNewStage($userId, $stageNumber, $duration, $spawnInterval);
    }

    /**
     * Mulai sesi stage baru secara paksa (menutup sesi lama jika masih aktif).
     */
    public function startNewStage(
        ?int $userId,
        int $stageNumber = 1,
        int $duration = self::DEFAULT_DURATION,
        int $spawnInterval = self::DEFAULT_SPAWN_INTERVAL
    ): StageSession {
        StageSession::where('user_id', $userId)
            ->where('status', 'active')
            ->update([
                'status'   => 'completed',
                'ended_at' => now(),
            ]);

        CustomerOrder::where('user_id', $userId)
            ->where('status', 'waiting')
            ->update(['status' => 'expired', 'patience_remaining' => 0]);

        $now = now();
        $stage = StageSession::create([
            'user_id'                => $userId,
            'stage_number'           => $stageNumber,
            'duration_seconds'       => $duration,
            'spawn_interval_seconds' => $spawnInterval,
            'started_at'             => $now,
            'ends_at'                => $now->copy()->addSeconds($duration),
            'status'                 => 'active',
        ]);

        // Munculkan 1 pelanggan awal agar pemain langsung memiliki pesanan saat stage mulai
        $this->servingService->spawnCustomer($userId, 0, $stage->id, $stageNumber);

        return $stage;
    }

    /**
     * Munculkan pesanan pelanggan baru secara berkala sesuai laju spawn stage (PBI-010).
     */
    public function spawnPeriodicOrder(?int $userId, int $stageSessionId): array
    {
        $stage = StageSession::where('id', $stageSessionId)
            ->where('user_id', $userId)
            ->first();

        if (!$stage) {
            return ['success' => false, 'message' => 'Sesi stage tidak ditemukan.'];
        }

        if ($stage->status !== 'active' || $stage->remainingSeconds() <= 0) {
            $recap = $this->finishStage($userId, $stage->id);

            return [
                'success'     => false,
                'stage_ended' => true,
                'message'     => 'Waktu sesi stage telah berakhir.',
                'recap'       => $recap['recap'] ?? null,
            ];
        }

        $this->syncExpiredOrders($stage);

        $waitingOrders = CustomerOrder::where('stage_session_id', $stage->id)
            ->where('status', 'waiting')
            ->orderBy('slot_index')
            ->get();

        // Batas maksimal antrean di counter (3 slot)
        if ($waitingOrders->count() >= ServingService::MAX_SLOTS) {
            return [
                'success'           => true,
                'spawned'           => false,
                'reason'            => 'Antrean pelanggan sudah penuh.',
                'remaining_seconds' => $stage->remainingSeconds(),
                'customers'         => $this->servingService->getStageCustomers($stage),
            ];
        }

        // Validasi laju spawn: jika antrean tidak kosong, wajib menunggu interval spawn_interval_seconds
        if ($waitingOrders->isNotEmpty()) {
            $lastOrder = CustomerOrder::where('stage_session_id', $stage->id)
                ->latest('spawned_at')
                ->latest('id')
                ->first();

            if ($lastOrder && $lastOrder->spawned_at) {
                $elapsed = (int) $lastOrder->spawned_at->diffInSeconds(now(), false);
                if ($elapsed < $stage->spawn_interval_seconds) {
                    return [
                        'success'           => true,
                        'spawned'           => false,
                        'reason'            => 'Belum mencapai interval spawn berikutnya.',
                        'next_spawn_in'     => $stage->spawn_interval_seconds - max(0, $elapsed),
                        'remaining_seconds' => $stage->remainingSeconds(),
                        'customers'         => $this->servingService->getStageCustomers($stage),
                    ];
                }
            }
        }

        // Cari slot kosong pertama (0..2)
        $occupiedSlots = $waitingOrders->pluck('slot_index')->toArray();
        $freeSlot = null;
        for ($i = 0; $i < ServingService::MAX_SLOTS; $i++) {
            if (!in_array($i, $occupiedSlots)) {
                $freeSlot = $i;
                break;
            }
        }

        if ($freeSlot === null) {
            return [
                'success'           => true,
                'spawned'           => false,
                'remaining_seconds' => $stage->remainingSeconds(),
                'customers'         => $this->servingService->getStageCustomers($stage),
            ];
        }

        $newOrder = $this->servingService->spawnCustomer($userId, $freeSlot, $stage->id, $stage->stage_number);

        return [
            'success'           => true,
            'spawned'           => (bool) $newOrder,
            'order'             => $newOrder ? $this->servingService->formatOrder($newOrder) : null,
            'remaining_seconds' => $stage->remainingSeconds(),
            'customers'         => $this->servingService->getStageCustomers($stage),
        ];
    }

    /**
     * Akhiri sesi stage saat timer mencapai 0 dan kembalikan rekapitulasi skor (PBI-010 AC 2).
     */
    public function finishStage(?int $userId, int $stageSessionId): array
    {
        $stage = StageSession::where('id', $stageSessionId)
            ->where('user_id', $userId)
            ->first();

        if (!$stage) {
            return ['success' => false, 'message' => 'Sesi stage tidak ditemukan.'];
        }

        if ($stage->status === 'active') {
            $this->syncExpiredOrders($stage);

            $stage->update([
                'status'   => 'completed',
                'ended_at' => now(),
            ]);

            // Tutup pesanan yang masih menunggu saat bel waktu habis tanpa menambah penalti expired
            CustomerOrder::where('stage_session_id', $stage->id)
                ->where('status', 'waiting')
                ->update(['status' => 'expired', 'patience_remaining' => 0]);

            $stage->refresh();
        }

        $user = $userId ? User::find($userId) : null;
        $penaltyScore = $stage->orders_expired * ServingService::EXPIRE_SCORE_PENALTY;
        $totalScore = max(0, $stage->coins_earned + ($stage->eco_points_earned * 2) - $penaltyScore);
        $stars = match (true) {
            $stage->orders_completed >= 5 => 3,
            $stage->orders_completed >= 2 => 2,
            $stage->orders_completed >= 1 => 1,
            default                       => 0,
        };

        return [
            'success' => true,
            'message' => 'Sesi stage berakhir! Berikut rekapitulasi skor Anda.',
            'recap'   => [
                'stage_session_id'  => $stage->id,
                'stage_number'      => $stage->stage_number,
                'duration_seconds'  => $stage->duration_seconds,
                'orders_completed'  => $stage->orders_completed,
                'orders_expired'    => $stage->orders_expired,
                'coins_earned'      => $stage->coins_earned,
                'eco_points_earned' => $stage->eco_points_earned,
                'xp_earned'         => $stage->xp_earned,
                'penalty_score'     => $penaltyScore,
                'total_score'       => $totalScore,
                'stars'             => $stars,
                'player_totals'     => [
                    'coins'      => $user->coins ?? $stage->coins_earned,
                    'eco_points' => $user->eco_points ?? $stage->eco_points_earned,
                    'xp'         => $user->xp ?? $stage->xp_earned,
                ],
            ],
        ];
    }

    /**
     * Format state sesi stage untuk dikirim ke view/JSON.
     */
    public function formatStageState(StageSession $stage): array
    {
        return [
            'id'                     => $stage->id,
            'stage_number'           => $stage->stage_number,
            'duration_seconds'       => $stage->duration_seconds,
            'spawn_interval_seconds' => $stage->spawn_interval_seconds,
            'remaining_seconds'      => $stage->remainingSeconds(),
            'status'                 => $stage->status,
            'orders_completed'       => $stage->orders_completed,
            'orders_expired'         => $stage->orders_expired,
            'coins_earned'           => $stage->coins_earned,
            'eco_points_earned'      => $stage->eco_points_earned,
            'xp_earned'              => $stage->xp_earned,
        ];
    }

    /**
     * Sinkronisasi pesanan yang kesabarannya sudah habis berdasarkan expires_at server.
     */
    protected function syncExpiredOrders(StageSession $stage): void
    {
        $expiredCount = CustomerOrder::where('stage_session_id', $stage->id)
            ->where('status', 'waiting')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status'             => 'expired',
                'patience_remaining' => 0,
            ]);

        if ($expiredCount > 0) {
            $stage->increment('orders_expired', $expiredCount);
            $this->servingService->applyExpirePenalty($stage->user_id, $expiredCount);
        }
    }
}
