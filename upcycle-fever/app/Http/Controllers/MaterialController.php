<?php

namespace App\Http\Controllers;

use App\Services\Game\InventoryService;
use App\Services\Game\ServingService;
use App\Services\Game\StageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Halaman Web Katalog / Master Data Bahan Limbah
     */
    public function index(Request $request): View
    {
        $category = $request->query('category');
        $search   = $request->query('search');

        $materials = $this->inventoryService->getMaterials([
            'category' => $category,
            'search'   => $search,
        ]);

        $categories = ['Semua', 'Plastik', 'Kertas/Karton', 'Logam', 'Tekstil', 'Kaca'];

        return view('materials.index', compact('materials', 'categories', 'category', 'search'));
    }

    /**
     * API Endpoint: Daftar Bahan Limbah (JSON)
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $materials = $this->inventoryService->getMaterials($request->all());

        return response()->json([
            'success' => true,
            'count'   => $materials->count(),
            'data'    => $materials,
        ]);
    }

    /**
     * API Endpoint: Tambah Data Sampah Baru (Master Data / Technical Task 1)
     */
    public function apiStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'category'    => 'required|string|max:50',
            'type'        => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'icon'        => 'nullable|string|max:10',
            'eco_impact'  => 'required|integer|min:1|max:100',
        ]);

        $material = $this->inventoryService->createMaterial($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data bahan sampah berhasil ditambahkan.',
            'data'    => $material,
        ], 201);
    }

    /**
     * Halaman Web Meja Kerja (Workbench) & Pemilihan Bahan dari Inventaris (PBI-004)
     */
    public function workbench(): View
    {
        $inventory  = $this->inventoryService->getUserInventory(auth()->id());
        $tools      = \App\Models\Tool::all();
        $recipes    = \App\Models\Recipe::with(['tool', 'ingredients'])->get();
        $servingSvc = app(ServingService::class);
        $stageSvc   = app(StageService::class);
        $stageModel = $stageSvc->getOrStartStage(auth()->id());
        $stage      = $stageSvc->formatStageState($stageModel);
        $customers  = $servingSvc->getStageCustomers($stageModel);
        $user       = auth()->user();

        return view('game.workbench', compact('inventory', 'tools', 'recipes', 'customers', 'stage', 'user'));
    }

    /**
     * API Endpoint: Ambil Inventaris Pemain saat ini (JSON)
     */
    public function apiInventory(): JsonResponse
    {
        $inventory = $this->inventoryService->getUserInventory(auth()->id());

        return response()->json([
            'success' => true,
            'count'   => $inventory->count(),
            'data'    => $inventory,
        ]);
    }

    /**
     * API Endpoint: Validasi Bahan yang Dipilih di Slot Meja Kerja
     */
    public function apiValidateWorkbench(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'material_ids'   => 'required|array|min:1|max:4',
            'material_ids.*' => 'integer|exists:materials,id',
        ]);

        $result = $this->inventoryService->validateWorkbench($validated['material_ids']);

        return response()->json(array_merge(['success' => true], $result));
    }
}

