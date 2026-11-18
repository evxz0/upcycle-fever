<?php

namespace App\Http\Controllers;

use App\Services\Game\ServingService;
use App\Services\Game\StageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StageController extends Controller
{
    public function __construct(
        protected StageService $stageService,
        protected ServingService $servingService
    ) {}

    /**
     * POST /api/game/stage/start — Mulai sesi stage baru dengan timer hitung mundur.
     */
    public function apiStart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stage_number'           => 'nullable|integer|min:1|max:20',
            'duration_seconds'       => 'nullable|integer|min:10|max:600',
            'spawn_interval_seconds' => 'nullable|integer|min:3|max:60',
        ]);

        $stage = $this->stageService->startNewStage(
            auth()->id(),
            $validated['stage_number'] ?? 1,
            $validated['duration_seconds'] ?? StageService::DEFAULT_DURATION,
            $validated['spawn_interval_seconds'] ?? StageService::DEFAULT_SPAWN_INTERVAL
        );

        return response()->json([
            'success'   => true,
            'stage'     => $this->stageService->formatStageState($stage),
            'customers' => $this->servingService->getStageCustomers($stage),
        ]);
    }

    /**
     * POST /api/game/stage/spawn — Munculkan pesanan pelanggan baru secara berkala (PBI-010).
     */
    public function apiSpawnOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stage_session_id' => 'required|integer|exists:stage_sessions,id',
        ]);

        $result = $this->stageService->spawnPeriodicOrder(
            auth()->id(),
            $validated['stage_session_id']
        );

        $status = $result['success'] ? 200 : (!empty($result['stage_ended']) ? 200 : 422);

        return response()->json($result, $status);
    }

    /**
     * POST /api/game/stage/finish — Akhiri stage & kembalikan rekapitulasi skor (PBI-010 AC 2).
     */
    public function apiFinish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stage_session_id' => 'required|integer|exists:stage_sessions,id',
        ]);

        $result = $this->stageService->finishStage(
            auth()->id(),
            $validated['stage_session_id']
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
