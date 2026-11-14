<?php

namespace App\Services\Game;

use App\Models\Inventory;
use App\Models\Material;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class InventoryService
{
    /**
     * Mengambil daftar master data bahan limbah (Katalog)
     */
    public function getMaterials(array $filters = []): Collection
    {
        $query = Material::query()->where('is_active', true);

        if (!empty($filters['category']) && $filters['category'] !== 'Semua') {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('category')->orderBy('name')->get();
    }

    /**
     * Tambah data sampah baru ke katalog (Master Data)
     */
    public function createMaterial(array $data): Material
    {
        return Material::create([
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']) . '-' . Str::random(4),
            'category'    => $data['category'],
            'type'        => $data['type'] ?? 'Anorganik',
            'description' => $data['description'] ?? null,
            'icon'        => $data['icon'] ?? '📦',
            'eco_impact'  => (int) ($data['eco_impact'] ?? 10),
            'is_active'   => true,
        ]);
    }

    /**
     * Mengambil daftar bahan di inventaris milik user
     */
    public function getUserInventory(?int $userId): Collection
    {
        if (!$userId) {
            // Jika Guest / belum login, kembalikan sample materials untuk demo trial
            return Material::where('is_active', true)->take(6)->get()->map(function ($material) {
                return (object) [
                    'id'          => $material->id,
                    'material_id' => $material->id,
                    'quantity'    => 3,
                    'material'    => $material,
                ];
            });
        }

        $items = Inventory::with('material')
            ->where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->get();

        // Jika user belum memiliki item inventaris sama sekali, seed starter pack otomatis
        if ($items->isEmpty()) {
            $this->seedStarterPack($userId);
            $items = Inventory::with('material')
                ->where('user_id', $userId)
                ->where('quantity', '>', 0)
                ->get();
        }

        return $items;
    }

    /**
     * Menambahkan starter pack inventaris untuk pemain baru
     */
    public function seedStarterPack(int $userId): void
    {
        $starterMaterials = Material::where('is_active', true)->take(5)->get();
        foreach ($starterMaterials as $idx => $material) {
            Inventory::firstOrCreate(
                [
                    'user_id'     => $userId,
                    'material_id' => $material->id,
                ],
                [
                    'quantity'    => 3 + $idx,
                ]
            );
        }
    }

    /**
     * Tambah jumlah bahan ke inventaris pemain
     */
    public function addToInventory(int $userId, int $materialId, int $quantity = 1): Inventory
    {
        $inv = Inventory::firstOrNew([
            'user_id'     => $userId,
            'material_id' => $materialId,
        ]);

        $inv->quantity = ($inv->quantity ?? 0) + $quantity;
        $inv->save();

        return $inv->load('material');
    }

    /**
     * Kurangi jumlah bahan dari inventaris pemain
     */
    public function deductFromInventory(int $userId, int $materialId, int $quantity = 1): bool
    {
        $inv = Inventory::where('user_id', $userId)
            ->where('material_id', $materialId)
            ->first();

        if (!$inv || $inv->quantity < $quantity) {
            return false;
        }

        $inv->quantity -= $quantity;
        if ($inv->quantity <= 0) {
            $inv->delete();
        } else {
            $inv->save();
        }

        return true;
    }

    /**
     * Validasi slot workbench
     */
    public function validateWorkbench(array $materialIds): array
    {
        if (empty($materialIds)) {
            return [
                'valid'   => false,
                'message' => 'Meja kerja masih kosong. Pilih minimal 1 bahan dari inventaris.',
                'items'   => [],
            ];
        }

        $materials = Material::whereIn('id', $materialIds)->get();

        return [
            'valid'            => true,
            'count'            => count($materials),
            'items'            => $materials,
            'total_eco_impact' => $materials->sum('eco_impact'),
        ];
    }
}

