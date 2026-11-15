<?php

namespace App\Services\Game;

use App\Models\Material;
use App\Models\Recipe;
use App\Models\Tool;
use Illuminate\Database\Eloquent\Collection;

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
     * Memproses perakitan bahan di meja kerja (PBI-005)
     */
    public function craft(?int $userId, array $materialIds, int $toolId): array
    {
        $tool = Tool::find($toolId);
        if (!$tool) {
            return [
                'success' => false,
                'message' => 'Pilih salah satu alat kerja terlebih dahulu sebelum merakit.',
            ];
        }

        $recipe = $this->findMatchingRecipe($materialIds, $toolId);

        // Acceptance Criteria 2: Jika salah, tampilkan umpan balik kegagalan & tidak kurangi stok
        if (!$recipe) {
            return [
                'success' => false,
                'message' => 'Kombinasi bahan sampah dan alat tidak cocok dengan formula resep manapun. Periksa kembali panduan resep!',
            ];
        }

        // Acceptance Criteria 1: Jika cocok, produk valid dan potong bahan dari inventaris
        if ($userId) {
            $materialCounts = array_count_values($materialIds);
            foreach ($materialCounts as $matId => $qty) {
                $this->inventoryService->deductFromInventory($userId, $matId, $qty);
            }
        }

        return [
            'success' => true,
            'message' => "Selamat! Anda berhasil merakit {$recipe->name}!",
            'product' => [
                'id'          => $recipe->id,
                'name'        => $recipe->name,
                'output_icon' => $recipe->output_icon,
                'description' => $recipe->description,
                'eco_points'  => $recipe->eco_points,
                'coin_reward' => $recipe->coin_reward,
                'tool_name'   => $tool->name,
            ],
        ];
    }
}

