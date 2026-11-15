<?php

namespace App\Services\Game;

use App\Models\CustomerOrder;
use App\Models\Recipe;
use App\Models\User;

class ServingService
{
    protected const MAX_SLOTS = 3;

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
     * Ambil/isi 3 pelanggan aktif (waiting) di slot meja kasir.
     */
    public function getActiveCustomers(?int $userId): array
    {
        $active = CustomerOrder::where('user_id', $userId)
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

        return $active->sortBy('slot_index')->values()->map(fn($o) => [
            'id'                 => $o->id,
            'slot_index'         => $o->slot_index,
            'customer_name'      => $o->customer_name,
            'customer_avatar'    => $o->customer_avatar,
            'recipe_id'          => $o->recipe_id,
            'recipe_name'        => $o->recipe->name,
            'recipe_icon'        => $o->recipe->output_icon,
            'patience_max'       => $o->patience_max,
            'patience_remaining' => $o->patience_remaining,
            'status'             => $o->status,
        ])->toArray();
    }

    /**
     * Spawn pelanggan baru acak di slot tertentu.
     */
    protected function spawnCustomer(?int $userId, int $slotIndex): ?CustomerOrder
    {
        $recipe = Recipe::inRandomOrder()->first();
        if (!$recipe) return null;

        $persona = $this->customers[array_rand($this->customers)];
        $patience = rand(45, 75);

        return CustomerOrder::create([
            'user_id'            => $userId,
            'slot_index'         => $slotIndex,
            'customer_name'      => $persona['name'],
            'customer_avatar'    => $persona['avatar'],
            'recipe_id'          => $recipe->id,
            'patience_max'       => $patience,
            'patience_remaining' => $patience,
            'status'             => 'waiting',
        ]);
    }

    /**
     * Serahkan produk ke pelanggan (PBI-006 AC 1 & AC 2).
     */
    public function serveProduct(?int $userId, int $orderId, int $recipeId): array
    {
        $order = CustomerOrder::where('id', $orderId)
            ->where('user_id', $userId)
            ->where('status', 'waiting')
            ->first();

        if (!$order) {
            return [
                'success' => false,
                'message' => 'Pesanan tidak ditemukan atau sudah selesai.',
            ];
        }

        // Cek kesabaran habis
        if ($order->patience_remaining <= 0) {
            $order->update(['status' => 'expired']);
            $this->spawnCustomer($userId, $order->slot_index);
            return [
                'success' => false,
                'message' => 'Sayang sekali! Pelanggan sudah pergi karena terlalu lama menunggu.',
            ];
        }

        // Validasi produk yang diserahkan sesuai pesanan
        if ($order->recipe_id !== $recipeId) {
            return [
                'success' => false,
                'message' => 'Produk yang diserahkan tidak sesuai pesanan pelanggan ini!',
            ];
        }

        // AC 1: Pesanan berhasil — tandai selesai, beri reward
        $order->update(['status' => 'completed']);

        $coinReward = $order->recipe->coin_reward;
        $ecoReward  = $order->recipe->eco_points;

        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                $user->increment('coins', $coinReward);
                $user->increment('eco_points', $ecoReward);
                $user->increment('xp', 10);
            }
        }

        // AC 2: Slot kosong diisi pelanggan baru dari antrean
        $newOrder = $this->spawnCustomer($userId, $order->slot_index);

        return [
            'success'      => true,
            'message'      => "Pesanan {$order->recipe->name} berhasil diserahkan ke {$order->customer_name}!",
            'reward'       => [
                'coins'      => $coinReward,
                'eco_points' => $ecoReward,
                'xp'         => 10,
            ],
            'new_customer' => $newOrder ? [
                'id'              => $newOrder->id,
                'slot_index'      => $newOrder->slot_index,
                'customer_name'   => $newOrder->customer_name,
                'customer_avatar' => $newOrder->customer_avatar,
                'recipe_id'       => $newOrder->recipe_id,
                'recipe_name'     => $newOrder->recipe->name,
                'recipe_icon'     => $newOrder->recipe->output_icon,
                'patience_max'    => $newOrder->patience_max,
                'patience_remaining' => $newOrder->patience_remaining,
                'status'          => $newOrder->status,
            ] : null,
        ];
    }

    /**
     * Expire pelanggan yang kehabisan kesabaran.
     */
    public function expireCustomer(?int $userId, int $orderId): array
    {
        $order = CustomerOrder::where('id', $orderId)
            ->where('user_id', $userId)
            ->where('status', 'waiting')
            ->first();

        if (!$order) {
            return ['success' => false, 'message' => 'Pesanan tidak ditemukan.'];
        }

        $order->update(['status' => 'expired', 'patience_remaining' => 0]);

        $newOrder = $this->spawnCustomer($userId, $order->slot_index);

        return [
            'success'      => true,
            'message'      => "{$order->customer_name} pergi karena tidak sabar menunggu.",
            'new_customer' => $newOrder ? [
                'id'              => $newOrder->id,
                'slot_index'      => $newOrder->slot_index,
                'customer_name'   => $newOrder->customer_name,
                'customer_avatar' => $newOrder->customer_avatar,
                'recipe_id'       => $newOrder->recipe_id,
                'recipe_name'     => $newOrder->recipe->name,
                'recipe_icon'     => $newOrder->recipe->output_icon,
                'patience_max'    => $newOrder->patience_max,
                'patience_remaining' => $newOrder->patience_remaining,
                'status'          => $newOrder->status,
            ] : null,
        ];
    }
}

