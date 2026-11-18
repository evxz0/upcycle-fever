<?php

namespace App\Services\Ai;

use App\Models\Recipe;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
    }

    /**
     * F-01: Generate pesanan pelanggan dinamis via Gemini API.
     * Menerima respon JSON terstruktur: nama NPC, tingkat kesulitan, bahan sampah.
     * Fallback otomatis ke resep statis dari database jika API gagal/timeout.
     */
    public function generateOrder(int $stageNumber = 1, array $availableRecipes = []): array
    {
        if (empty($this->apiKey)) {
            Log::info('GeminiService: API key kosong, gunakan fallback statis.');
            return $this->staticOrderFallback($availableRecipes);
        }

        $recipeList = collect($availableRecipes)->map(fn($r) => [
            'id'   => $r['id'],
            'name' => $r['name'],
            'icon' => $r['icon'] ?? '🎁',
        ])->values()->toJson();

        $difficultyLabel = $stageNumber <= 3 ? 'mudah' : ($stageNumber <= 7 ? 'sedang' : 'sulit');

        $prompt = <<<PROMPT
Kamu adalah generator pesanan untuk game edukasi Upcycle Fever (tema daur ulang / upcycling, SDG 12).
Buat 1 pesanan pelanggan yang unik dan lucu untuk Stage {$stageNumber} (tingkat: {$difficultyLabel}).

Daftar resep yang tersedia (pilih SATU recipe_id dari daftar ini):
{$recipeList}

Balas HANYA dengan JSON valid (tanpa markdown, tanpa penjelasan), format persis:
{
  "customer_name": "<nama NPC Indonesia yang unik dan lucu, 1-3 kata>",
  "customer_avatar": "<1 emoji karakter>",
  "recipe_id": <id integer dari daftar di atas>,
  "difficulty": "<mudah|sedang|sulit>",
  "dialog": "<kalimat permintaan NPC, maksimal 20 kata, lucu dan edukatif>"
}
PROMPT;

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature'     => 0.9,
                    'maxOutputTokens' => 300,
                ],
            ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text', '');
                // Bersihkan markdown jika ada
                $text = preg_replace('/```json\s*|\s*```/', '', trim($text));

                $data = json_decode($text, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($data['recipe_id'])) {
                    // Validasi recipe_id ada di daftar
                    $validIds = array_column($availableRecipes, 'id');
                    if (!in_array($data['recipe_id'], $validIds)) {
                        $data['recipe_id'] = $validIds[array_rand($validIds)] ?? null;
                    }

                    Log::info('GeminiService: Berhasil generate pesanan dinamis.', ['customer' => $data['customer_name']]);
                    return array_merge($this->staticOrderFallback($availableRecipes), $data);
                }
            }

            Log::warning('GeminiService: Response tidak valid, gunakan fallback.', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            Log::warning('GeminiService: Timeout/error, gunakan fallback.', ['error' => $e->getMessage()]);
        }

        // Fallback otomatis — sesi game tidak crash (AC F-01 fallback)
        return $this->staticOrderFallback($availableRecipes);
    }

    /**
     * Fallback statis: ambil resep acak dari database agar game tetap berjalan
     * saat Gemini API gagal atau kuota habis (Acceptance Criteria F-01 fallback).
     */
    public function staticOrderFallback(array $availableRecipes = []): array
    {
        $personas = [
            ['name' => 'Pak Kumis',    'avatar' => '👨'],
            ['name' => 'Ibu Gaul',     'avatar' => '👩'],
            ['name' => 'Adik Pintar',  'avatar' => '🧒'],
            ['name' => 'Nenek Hijau',  'avatar' => '👵'],
            ['name' => 'Om Kreatif',   'avatar' => '🧔'],
            ['name' => 'Kak Ceria',    'avatar' => '👧'],
            ['name' => 'Dosen Eco',    'avatar' => '🧑‍🏫'],
            ['name' => 'Si Kembar',    'avatar' => '👦'],
            ['name' => 'Prof. Sampah', 'avatar' => '🧑‍🔬'],
            ['name' => 'Eco Ranger',   'avatar' => '🦸'],
        ];

        $dialogs = [
            'Tolong buatkan pesanan saya ya, bahan bekasnya jangan dibuang!',
            'Saya butuh produk daur ulang terbaik dari studio ini!',
            'Yuk kita kurangi sampah dengan membuat sesuatu yang keren!',
            'Hayo cepat, saya sudah tidak sabar menunggu!',
            'SDG 12 itu penting lho, mari kita mulai dari sini!',
        ];

        $persona = $personas[array_rand($personas)];

        // Pilih resep dari daftar yang diberikan, atau dari DB sebagai fallback terakhir
        if (!empty($availableRecipes)) {
            $recipe = $availableRecipes[array_rand($availableRecipes)];
            $recipeId = $recipe['id'];
        } else {
            $dbRecipe = Recipe::inRandomOrder()->first();
            $recipeId = $dbRecipe?->id;
        }

        return [
            'customer_name'   => $persona['name'],
            'customer_avatar'  => $persona['avatar'],
            'recipe_id'        => $recipeId,
            'difficulty'       => 'mudah',
            'dialog'           => $dialogs[array_rand($dialogs)],
            'is_fallback'      => true,
        ];
    }

    /**
     * Generate konten umum (quiz, eco-tips, dll).
     */
    public function generate(string $prompt, array $fallback = []): array
    {
        if (empty($this->apiKey)) {
            return $fallback ?: ['text' => 'Konten tidak tersedia saat ini.'];
        }

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text', '');
                return ['text' => $text];
            }
        } catch (\Throwable $e) {
            Log::warning('GeminiService::generate failed: ' . $e->getMessage());
        }

        return $fallback ?: ['text' => 'Konten tidak tersedia saat ini. Coba lagi nanti.'];
    }
}
