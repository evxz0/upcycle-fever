<?php

namespace App\Http\Controllers;

use App\Services\Game\CraftingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CraftingController extends Controller
{
    public function __construct(
        protected CraftingService $craftingService
    ) {}

    /**
     * API Endpoint: Proses perakitan produk di Meja Kerja (PBI-005)
     */
    public function apiCraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'material_ids'   => 'required|array|min:1|max:4',
            'material_ids.*' => 'integer|exists:materials,id',
            'tool_id'        => 'required|integer|exists:tools,id',
        ]);

        $userId = auth()->id();
        $result = $this->craftingService->craft(
            $userId,
            $validated['material_ids'],
            $validated['tool_id']
        );

        $status = $result['success'] ? 200 : 422;

        return response()->json($result, $status);
    }

    /**
     * API Endpoint: Daftar Resep Upcycling
     */
    public function apiRecipes(): JsonResponse
    {
        $recipes = $this->craftingService->getRecipes();

        return response()->json([
            'success' => true,
            'count'   => $recipes->count(),
            'data'    => $recipes,
        ]);
    }

    /**
     * API Endpoint: Daftar Alat Kerja (Tools)
     */
    public function apiTools(): JsonResponse
    {
        $tools = $this->craftingService->getTools();

        return response()->json([
            'success' => true,
            'count'   => $tools->count(),
            'data'    => $tools,
        ]);
    }
}
