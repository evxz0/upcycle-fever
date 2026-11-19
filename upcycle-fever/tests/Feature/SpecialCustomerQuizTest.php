<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Game\QuizService;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\RecipeSeeder;
use Database\Seeders\ToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialCustomerQuizTest extends TestCase
{
    use RefreshDatabase;

    protected User $player;
    protected QuizService $quizService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MaterialSeeder::class);
        $this->seed(ToolSeeder::class);
        $this->seed(RecipeSeeder::class);

        $this->player = User::factory()->create([
            'role'       => 'player',
            'is_active'  => true,
            'coins'      => 100,
            'eco_points' => 50,
            'xp'         => 0,
        ]);

        $this->quizService = app(QuizService::class);
    }

    /**
     * PBI-013: Bank soal kuis minimal 10 soal bertema limbah & SDG 12
     */
    public function test_quiz_question_bank_has_at_least_ten_valid_questions(): void
    {
        $questions = $this->quizService->loadQuestions();

        $this->assertGreaterThanOrEqual(10, count($questions), 'Bank soal harus memiliki minimal 10 soal');

        foreach ($questions as $q) {
            $this->assertArrayHasKey('id', $q);
            $this->assertArrayHasKey('question', $q);
            $this->assertArrayHasKey('options', $q);
            $this->assertArrayHasKey('correct', $q);
            $this->assertArrayHasKey('explanation', $q);

            $this->assertNotEmpty($q['question']);
            $this->assertGreaterThanOrEqual(3, count($q['options']), 'Setiap soal harus memiliki minimal 3 opsi');
            $this->assertLessThanOrEqual(4, count($q['options']), 'Setiap soal maksimal memiliki 4 opsi');
            $this->assertIsInt($q['correct']);
            $this->assertArrayHasKey($q['correct'], $q['options'], 'Jawaban benar harus berada dalam rentang indeks options');
            $this->assertNotEmpty($q['explanation']);
        }
    }

    /**
     * PBI-013: Endpoint API mengambil soal acak tanpa membocorkan kunci jawaban
     */
    public function test_api_returns_random_question_without_exposing_correct_answer(): void
    {
        $response = $this->actingAs($this->player)->getJson(route('api.game.quiz.question'));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'question' => [
                    'id',
                    'question',
                    'options',
                    'explanation',
                ],
            ]);

        // Pastikan indeks jawaban benar tidak bocor di endpoint pengambilan soal
        $this->assertArrayNotHasKey('correct', $response->json('question'));
    }

    /**
     * PBI-013: Soal dipilih tanpa pengulangan berurutan berdasarkan used_ids
     */
    public function test_api_avoids_repeated_questions_when_used_ids_provided(): void
    {
        $questions = $this->quizService->loadQuestions();
        $allIds = array_column($questions, 'id');

        // Gunakan hampir semua ID kecuali satu
        $lastId = array_pop($allIds);

        $response = $this->actingAs($this->player)->getJson(
            route('api.game.quiz.question', ['used_ids' => $allIds])
        );

        $response->assertStatus(200)
            ->assertJsonPath('question.id', $lastId);
    }

    /**
     * PBI-014: Validasi jawaban benar memberikan bonus perlambatan patience
     */
    public function test_submitting_correct_answer_awards_patience_slowdown_bonus(): void
    {
        $question = $this->quizService->getQuestionById(1);
        $this->assertNotNull($question);

        $response = $this->actingAs($this->player)->postJson(route('api.game.quiz.answer'), [
            'question_id'  => 1,
            'answer_index' => $question['correct'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_correct', true)
            ->assertJsonPath('correct_index', $question['correct'])
            ->assertJsonPath('modifier.factor', QuizService::BONUS_FACTOR)
            ->assertJsonPath('modifier.duration', QuizService::BONUS_DURATION)
            ->assertJsonPath('modifier.type', 'bonus');
    }

    /**
     * PBI-015: Validasi jawaban salah memberikan penalti percepatan patience dan penjelasan edukatif
     */
    public function test_submitting_wrong_answer_applies_patience_speedup_penalty_and_shows_explanation(): void
    {
        $question = $this->quizService->getQuestionById(1);
        $this->assertNotNull($question);

        // Pilih opsi yang salah
        $wrongIndex = ($question['correct'] + 1) % count($question['options']);

        $response = $this->actingAs($this->player)->postJson(route('api.game.quiz.answer'), [
            'question_id'  => 1,
            'answer_index' => $wrongIndex,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_correct', false)
            ->assertJsonPath('correct_index', $question['correct'])
            ->assertJsonPath('modifier.factor', QuizService::PENALTY_FACTOR)
            ->assertJsonPath('modifier.duration', QuizService::PENALTY_DURATION)
            ->assertJsonPath('modifier.type', 'penalty');

        $this->assertEquals($question['explanation'], $response->json('explanation'));
    }

    /**
     * PBI-013 & PBI-014: Workbench merender komponen visual pelanggan spesial dan modal kuis
     */
    public function test_workbench_renders_special_customer_and_quiz_modal_components(): void
    {
        $response = $this->actingAs($this->player)->get(route('game.workbench'));

        $response->assertStatus(200);
        $response->assertSee('specialCustomer', false);
        $response->assertSee('data-testid="special-customer-card"', false);
        $response->assertSee('data-testid="quiz-modal"', false);
        $response->assertSee('openQuizModal()', false);
        $response->assertSee('submitQuizAnswer(idx)', false);
        $response->assertSee('patienceModifier', false);
    }

    /**
     * Konfigurasi kuis dapat diambil melalui API
     */
    public function test_api_returns_quiz_configuration(): void
    {
        $response = $this->actingAs($this->player)->getJson(route('api.game.quiz.config'));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('config.spawn_chance', QuizService::SPAWN_CHANCE)
            ->assertJsonPath('config.bonus_factor', QuizService::BONUS_FACTOR)
            ->assertJsonPath('config.bonus_duration', QuizService::BONUS_DURATION)
            ->assertJsonPath('config.penalty_factor', QuizService::PENALTY_FACTOR)
            ->assertJsonPath('config.penalty_duration', QuizService::PENALTY_DURATION);
    }
}
