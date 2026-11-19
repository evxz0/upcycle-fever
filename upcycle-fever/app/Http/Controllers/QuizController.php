<?php

namespace App\Http\Controllers;

use App\Services\Game\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(
        protected QuizService $quizService
    ) {}

    /**
     * GET /api/game/quiz/question — Ambil satu soal acak untuk pelanggan spesial.
     */
    public function apiGetQuestion(Request $request): JsonResponse
    {
        $usedIds = $request->input('used_ids', []);

        if (is_string($usedIds)) {
            $usedIds = json_decode($usedIds, true) ?? [];
        }

        $question = $this->quizService->getRandomQuestion($usedIds);

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada soal kuis yang tersedia.',
            ], 404);
        }

        return response()->json([
            'success'  => true,
            'question' => $question,
        ]);
    }

    /**
     * POST /api/game/quiz/answer — Validasi jawaban kuis dan hitung modifier patience.
     */
    public function apiSubmitAnswer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question_id'  => 'required|integer',
            'answer_index' => 'required|integer|min:0|max:3',
        ]);

        $result = $this->quizService->validateAnswer(
            $validated['question_id'],
            $validated['answer_index']
        );

        if ($result['error'] ?? false) {
            return response()->json([
                'success' => false,
                'message' => $result['explanation'],
            ], 422);
        }

        $modifier = $this->quizService->calculatePatienceModifier($result['is_correct']);

        return response()->json([
            'success'       => true,
            'is_correct'    => $result['is_correct'],
            'correct_index' => $result['correct_index'],
            'explanation'   => $result['explanation'],
            'modifier'      => $modifier,
        ]);
    }

    /**
     * GET /api/game/quiz/config — Ambil konfigurasi kuis untuk frontend.
     */
    public function apiGetConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'config'  => $this->quizService->getConfig(),
        ]);
    }
}
