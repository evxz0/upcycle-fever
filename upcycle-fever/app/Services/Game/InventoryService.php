<?php

namespace App\Services\Game;

use App\Models\InventoryItem;
use App\Models\Material;
use App\Models\Recipe;
use Illuminate\Support\Collection;
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
     * Mengambil daftar bahan di inventaris milik user (PBI-008 AC 1 & 2)
     * Mengembalikan item dengan quantity >= 0 agar item yang habis (stok == 0) tetap tampil dengan status disabled.
     */
    public function getUserInventory(?int $userId): Collection
    {
        if (!$userId) {
            // Jika Guest / belum login, kembalikan sample materials untuk demo trial (tanpa DB)
            return Material::where('is_active', true)->take(6)->get()->map(function ($material) {
                return (object) [
                    'id'          => $material->id,
                    'material_id' => $material->id,
                    'quantity'    => 3,
                    'item_type'   => 'material',
                    'material'    => $material,
                ];
            });
        }

        $items = InventoryItem::with('material')
            ->where('user_id', $userId)
            ->where('item_type', 'material')
            ->get();

        // Jika user belum memiliki item inventaris sama sekali, seed starter pack otomatis
        if ($items->isEmpty()) {
            $this->seedStarterPack($userId);
            $items = InventoryItem::with('material')
                ->where('user_id', $userId)
                ->where('item_type', 'material')
                ->get();
        }

        return $items;
    }

    /**
     * Mengambil daftar produk jadi di inventaris milik user
     */
    public function getUserProducts(int $userId): Collection
    {
        return InventoryItem::with('recipe')
            ->where('user_id', $userId)
            ->where('item_type', 'product')
            ->where('quantity', '>', 0)
            ->get();
    }

    /**
     * Menambahkan starter pack inventaris untuk pemain baru
     */
    public function seedStarterPack(int $userId): void
    {
        $starterMaterials = Material::where('is_active', true)->take(6)->get();
        foreach ($starterMaterials as $idx => $material) {
            InventoryItem::firstOrCreate(
                [
                    'user_id'     => $userId,
                    'item_type'   => 'material',
                    'material_id' => $material->id,
                    'recipe_id'   => null,
                ],
                [
                    'quantity'    => 3 + $idx,
                ]
            );
        }
    }

    /**
     * Tambah jumlah bahan/produk ke inventaris pemain
     */
    public function addToInventory(
        int $userId,
        int $materialId,
        int $quantity = 1,
        string $itemType = 'material',
        ?int $recipeId = null
    ): InventoryItem {
        $inv = InventoryItem::firstOrNew([
            'user_id'     => $userId,
            'item_type'   => $itemType,
            'material_id' => $materialId,
            'recipe_id'   => $recipeId,
        ]);

        $inv->quantity = ($inv->quantity ?? 0) + $quantity;
        $inv->save();

        return $inv->load($itemType === 'material' ? 'material' : 'recipe');
    }

    /**
     * Tambah produk jadi ke inventaris pemain (PBI-009 AC 1)
     */
    public function addProductToInventory(int $userId, int $recipeId, int $quantity = 1): InventoryItem
    {
        $inv = InventoryItem::firstOrNew([
            'user_id'     => $userId,
            'item_type'   => 'product',
            'material_id' => null,
            'recipe_id'   => $recipeId,
        ]);

        $inv->quantity = ($inv->quantity ?? 0) + $quantity;
        $inv->save();

        return $inv->load('recipe');
    }

    /**
     * Kurangi jumlah bahan dari inventaris pemain (PBI-009 AC 1)
     * Tetap mempertahankan record dengan quantity = 0 agar status disabled ter-render di frontend.
     */
    public function deductFromInventory(int $userId, int $materialId, int $quantity = 1): bool
    {
        $inv = InventoryItem::where('user_id', $userId)
            ->where('item_type', 'material')
            ->where('material_id', $materialId)
            ->first();

        if (!$inv || $inv->quantity < $quantity) {
            return false;
        }

        $inv->quantity = max(0, $inv->quantity - $quantity);
        $inv->save();

        return true;
    }

    /**
     * Validasi ketersediaan stok bahan di inventaris pemain (PBI-009 AC 2)
     */
    public function hasSufficientStock(int $userId, int $materialId, int $requiredQuantity): bool
    {
        $stock = InventoryItem::where('user_id', $userId)
            ->where('item_type', 'material')
            ->where('material_id', $materialId)
            ->value('quantity') ?? 0;

        return $stock >= $requiredQuantity;
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
