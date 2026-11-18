<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Services\Game\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Halaman Antarmuka Gudang & Penyortiran Sampah (F-03 Trash Sorting)
     * Menampilkan kategori terpisah: Plastik, Kardus/Kertas, Logam, Kain Perca
     */
    public function index(Request $request): View
    {
        $userId = auth()->id();
        $inventory = $this->inventoryService->getUserInventory($userId);
        $allMaterials = Material::where('is_active', true)->get();

        // Kategori utama sesuai Acceptance Criteria F-03
        $categories = [
            'Plastik'       => ['icon' => '🧴', 'desc' => 'Botol PET, Tutup Botol HDPE, Kantong Plastik'],
            'Kertas/Karton' => ['icon' => '📦', 'desc' => 'Kardus Karton Bekas, Koran & Majalah'],
            'Logam'         => ['icon' => '🥫', 'desc' => 'Kaleng Minuman Aluminium, Tutup Kaleng'],
            'Tekstil'       => ['icon' => '🧵', 'desc' => 'Kain Perca Denim/Katun, Benang'],
        ];

        return view('inventory.index', compact('inventory', 'allMaterials', 'categories'));
    }

    /**
     * API Endpoint: Sortir & Tambah pasokan bahan baru ke kategori masing-masing (F-03 AC 2)
     */
    public function apiSortAndAdd(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'material_id' => 'required|integer|exists:materials,id',
            'category'    => 'required|string',
            'quantity'    => 'required|integer|min:1|max:50',
        ]);

        $material = Material::findOrFail($validated['material_id']);

        // Verifikasi keakuratan kategori saat menyortir (Trash Sorting Game/Logic)
        $normalizedInputCat = strtolower(str_replace('/', '', $validated['category']));
        $normalizedMaterialCat = strtolower(str_replace('/', '', $material->category));

        $isMatch = str_contains($normalizedMaterialCat, $normalizedInputCat) || 
                   str_contains($normalizedInputCat, $normalizedMaterialCat) ||
                   ($validated['category'] === 'Kain Perca' && $material->category === 'Tekstil') ||
                   ($validated['category'] === 'Kardus' && str_contains($material->category, 'Kertas'));

        if (!$isMatch) {
            return response()->json([
                'success' => false,
                'message' => "Kategori sampah tidak cocok! {$material->name} termasuk kategori {$material->category}.",
            ], 422);
        }

        $userId = auth()->id();
        if ($userId) {
            $invItem = $this->inventoryService->addToInventory(
                $userId,
                $material->id,
                $validated['quantity']
            );
        }

        return response()->json([
            'success'  => true,
            'message'  => "Berhasil menyortir dan menambahkan +{$validated['quantity']} {$material->name} ke kategori {$material->category}!",
            'material' => $material,
            'quantity' => $validated['quantity'],
        ]);
    }
}
