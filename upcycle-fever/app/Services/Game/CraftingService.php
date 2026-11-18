<?php

namespace App\Services\Game;

use App\Models\InventoryItem;
use App\Models\Material;
use App\Models\Recipe;
use App\Models\Tool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CraftingService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function getTools(): Collection
    {
        return Tool::all();
    }

    public function getRecipes(): Collection
    {
        return Recipe::with(['tool', 'ingredients'])->get();
    }

    /**
     * Cari resep yang cocok berdasarkan kumpulan material_ids dan tool_id
     */
    public function findMatchingRecipe(array $materialIds, int $toolId): ?Recipe
    {
        if (empty($materialIds) || empty($toolId)) {
            return null;
        }

        $materialCounts = array_count_values($materialIds);
        ksort($materialCounts);

        $recipes = Recipe::with('ingredients')->where('tool_id', $toolId)->get();

        foreach ($recipes as $recipe) {
            $requiredCounts = [];
            foreach ($recipe->ingredients as $ingredient) {
                $requiredCounts[$ingredient->id] = (int) $ingredient->pivot->quantity;
            }
            ksort($requiredCounts);

            if ($materialCounts === $requiredCounts) {
                return $recipe;
            }
        }

        return null;
    }

    /**
     * Memproses perakitan bahan di meja kerja (PBI-008 & PBI-009)
     * Menggunakan DB::transaction() agar pengurangan bahan & penambahan produk jadi bersifat atomik.
     */
    public function craft(?int $userId, array $materialIds, int $toolId): array
    {
        $tool = Tool::find($toolId);
        if (!$tool) {
            return [
                'success' => false,
                'message' => 'Pilih salah satu perkakas kerja terlebih dahulu sebelum merakit.',
            ];
        }

        $recipe = $this->findMatchingRecipe($materialIds, $toolId);

        // PBI-009 AC 2 / PBI-005 AC 2: Jika kombinasi salah, sistem menolak dan tidak mengurangi stok
        if (!$recipe) {
            return [
                'success' => false,
                'message' => 'Kombinasi bahan sampah dan perkakas tidak cocok dengan formula resep manapun. Periksa kembali buku panduan resep!',
            ];
        }

        // PBI-009 AC 1 & AC 2: Validasi stok dan transaksi atomik untuk pengguna terdaftar
        if ($userId) {
            $materialCounts = array_count_values($materialIds);

            // Validasi kecukupan stok seluruh bahan terlebih dahulu (PBI-009 AC 2)
            foreach ($materialCounts as $matId => $requiredQty) {
                $currentStock = InventoryItem::where('user_id', $userId)
                    ->where('item_type', 'material')
                    ->where('material_id', $matId)
                    ->value('quantity') ?? 0;

                if ($currentStock < $requiredQty) {
                    $mat = Material::find($matId);
                    $matName = $mat ? $mat->name : "ID #{$matId}";
                    return [
                        'success' => false,
                        'message' => "Gagal merakit: Stok bahan '{$matName}' tidak mencukupi di inventaris (tersisa: {$currentStock}, dibutuhkan: {$requiredQty}).",
                    ];
                }
            }

            // Eksekusi atomik pengurangan stok & penambahan produk jadi (PBI-009 AC 1)
            DB::transaction(function () use ($userId, $materialCounts, $recipe) {
                foreach ($materialCounts as $matId => $qty) {
                    $this->inventoryService->deductFromInventory($userId, $matId, $qty);
                }
                // Tambah produk jadi ke inventaris pemain
                $this->inventoryService->addProductToInventory($userId, $recipe->id, 1);
            });
        }

        // Ambil stok inventaris terkini untuk pembaruan reaktif frontend tanpa reload penuh
        $updatedInventory = $this->inventoryService->getUserInventory($userId);

        return [
            'success'           => true,
            'message'           => "Selamat! Anda berhasil merakit {$recipe->name}!",
            'product'           => [
                'id'          => $recipe->id,
                'name'        => $recipe->name,
                'output_icon' => $recipe->output_icon,
                'description' => $recipe->description,
                'eco_points'  => $recipe->eco_points,
                'coin_reward' => $recipe->coin_reward,
                'tool_name'   => $tool->name,
            ],
            'updated_inventory' => $updatedInventory,
        ];
    }
}
