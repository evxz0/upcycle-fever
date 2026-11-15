<?php

namespace App\Http\Controllers;

use App\Services\Game\ServingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServingController extends Controller
{
    public function __construct(
        protected ServingService $servingService
    ) {}

    /**
     * GET /api/game/customers — Ambil 3 pelanggan aktif di slot meja studio.
     */
    public function apiCustomers(): JsonResponse
    {
        $customers = $this->servingService->getActiveCustomers(auth()->id());

        return response()->json([
            'success' => true,
            'data'    => $customers,
        ]);
    }

    /**
     * POST /api/game/serve — Serahkan produk ke pelanggan (PBI-006).
     */
    public function apiServe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id'  => 'required|integer|exists:customer_orders,id',
            'recipe_id' => 'required|integer|exists:recipes,id',
        ]);

        $result = $this->servingService->serveProduct(
            auth()->id(),
            $validated['order_id'],
            $validated['recipe_id']
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/game/customers/expire — Pelanggan pergi (kesabaran habis).
     */
    public function apiExpire(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:customer_orders,id',
        ]);

        $result = $this->servingService->expireCustomer(
            auth()->id(),
            $validated['order_id']
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}

