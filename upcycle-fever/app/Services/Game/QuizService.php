<?php

namespace App\Services\Game;

use Illuminate\Support\Facades\File;

class QuizService
{
    /**
     * Konstanta konfigurasi kuis — semua angka dikumpulkan di satu tempat agar mudah diubah.
     */
    public const SPAWN_CHANCE          = 0.30;  // 30% peluang spawn per check
    public const SPAWN_MIN_PERCENT     = 30;    // Muncul setelah 30% durasi stage terlewati
    public const SPAWN_MAX_PERCENT     = 70;    // Muncul sebelum 70% durasi stage terlewati
    public const BONUS_DURATION        = 10;    // Detik durasi bonus perlambatan
    public const BONUS_FACTOR          = 0.5;   // Faktor pengali patience rate saat bonus (lebih lambat)
    public const PENALTY_DURATION      = 5;     // Detik durasi penalti percepatan
    public const PENALTY_FACTOR        = 1.5;   // Faktor pengali patience rate saat penalti (lebih cepat)

    /**
     * Cache soal yang sudah dimuat dari JSON.
     */
    protected ?array $questions = null;

    /**
     * Muat bank soal dari file JSON.
     */
    public function loadQuestions(): array
    {
        if ($this->questions !== null) {
            return $this->questions;
        }

        $path = database_path('data/quiz_questions.json');

        if (!File::exists($path)) {
            return $this->questions = [];
        }

        $json = File::get($path);
        $decoded = json_decode($json, true);

        $this->questions = is_array($decoded) ? $decoded : [];

        return $this->questions;
    }

    /**
     * Ambil satu soal acak, menghindari ID yang sudah dipakai (tanpa pengulangan berurutan).
     *
     * @param  array  $usedIds  Daftar ID soal yang sudah dipakai di sesi saat ini.
     * @return array|null       Soal lengkap atau null jika bank soal habis.
     */
    public function getRandomQuestion(array $usedIds = []): ?array
    {
        $questions = $this->loadQuestions();

        if (empty($questions)) {
            return null;
        }

        // Filter soal yang belum pernah dipakai
        $available = array_filter($questions, fn($q) => !in_array($q['id'], $usedIds));

        // Jika semua soal sudah dipakai, reset dan pakai semua kembali
        if (empty($available)) {
            $available = $questions;
        }

        $selected = $available[array_rand($available)];

        // Kembalikan soal tanpa jawaban benar (untuk dikirim ke frontend)
        return [
            'id'          => $selected['id'],
            'question'    => $selected['question'],
            'options'     => $selected['options'],
            'explanation' => $selected['explanation'],
        ];
    }

    /**
     * Ambil soal lengkap berdasarkan ID (termasuk jawaban benar, untuk validasi).
     */
    public function getQuestionById(int $id): ?array
    {
        $questions = $this->loadQuestions();

        foreach ($questions as $q) {
            if ($q['id'] === $id) {
                return $q;
            }
        }

        return null;
    }

    /**
     * Validasi jawaban pemain.
     *
     * @param  int  $questionId   ID soal.
     * @param  int  $answerIndex  Index jawaban yang dipilih pemain (0-based).
     * @return array              Hasil validasi: is_correct, correct_index, explanation.
     */
    public function validateAnswer(int $questionId, int $answerIndex): array
    {
        $question = $this->getQuestionById($questionId);

        if (!$question) {
            return [
                'is_correct'    => false,
                'correct_index' => null,
                'explanation'   => 'Soal tidak ditemukan.',
                'error'         => true,
            ];
        }

        $isCorrect = $answerIndex === $question['correct'];

        return [
            'is_correct'    => $isCorrect,
            'correct_index' => $question['correct'],
            'explanation'   => $question['explanation'],
            'error'         => false,
        ];
    }

    /**
     * Hitung modifier patience berdasarkan hasil jawaban.
     * Mengembalikan faktor dan durasi untuk diterapkan pada game loop.
     */
    public function calculatePatienceModifier(bool $isCorrect): array
    {
        if ($isCorrect) {
            return [
                'factor'   => self::BONUS_FACTOR,
                'duration' => self::BONUS_DURATION,
                'type'     => 'bonus',
            ];
        }

        return [
            'factor'   => self::PENALTY_FACTOR,
            'duration' => self::PENALTY_DURATION,
            'type'     => 'penalty',
        ];
    }

    /**
     * Ambil semua konfigurasi kuis sebagai array (untuk dikirim ke frontend).
     */
    public function getConfig(): array
    {
        return [
            'spawn_chance'      => self::SPAWN_CHANCE,
            'spawn_min_percent' => self::SPAWN_MIN_PERCENT,
            'spawn_max_percent' => self::SPAWN_MAX_PERCENT,
            'bonus_duration'    => self::BONUS_DURATION,
            'bonus_factor'      => self::BONUS_FACTOR,
            'penalty_duration'  => self::PENALTY_DURATION,
            'penalty_factor'    => self::PENALTY_FACTOR,
        ];
    }
}
