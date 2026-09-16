<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\StressAssessment;
use App\Models\StressQuestion;
use App\Models\StressResponse;
use App\Models\StressScoreBand;
use App\Models\User;
use App\Services\AssessmentAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssessmentAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_returns_only_the_compact_snapshot_data(): void
    {
        $band = $this->band('high', 'High');
        $this->assessment($band, 72);

        $data = app(AssessmentAnalytics::class)->overview();

        $this->assertSame(['totals', 'averages', 'stress_bands'], array_keys($data));
        $this->assertSame(1, $data['totals']['completed']);
        $this->assertSame(72.0, $data['averages']['stress_score']);
        $this->assertSame([['label' => 'High', 'total' => 1]], $data['stress_bands']->all());
    }

    public function test_question_tab_returns_only_question_aggregates(): void
    {
        $band = $this->band('moderate', 'Moderate');
        $assessment = $this->assessment($band, 45);
        $question = StressQuestion::query()->create([
            'question_text' => 'I can focus on my studies.',
            'question_type' => 'scale',
            'is_active' => true,
        ]);
        StressResponse::query()->create([
            'stress_assessment_id' => $assessment->id,
            'stress_question_id' => $question->id,
            'question_text_snapshot' => $question->question_text,
            'option_text_snapshot' => 'Often',
            'score' => 4,
            'scored_value' => 4,
        ]);

        $data = app(AssessmentAnalytics::class)->forTab('questions');

        $this->assertSame(['questions'], array_keys($data));
        $this->assertSame('I can focus on my studies.', $data['questions']->first()['question']);
        $this->assertSame(4.0, $data['questions']->first()['avg_score']);
    }

    public function test_overall_band_distributions_fetch_band_labels_in_two_queries(): void
    {
        foreach ([
            $this->band('low', 'Low'),
            $this->band('moderate', 'Moderate'),
            $this->band('high', 'High'),
        ] as $index => $band) {
            $this->assessment($band, ($index + 1) * 20);
        }

        $bandQueries = 0;
        DB::listen(function ($query) use (&$bandQueries): void {
            if (str_contains(strtolower($query->sql), 'stress_score_bands')) {
                $bandQueries++;
            }
        });

        app(AssessmentAnalytics::class)->forTab('overall');

        $this->assertSame(2, $bandQueries);
    }

    private function band(string $code, string $label): StressScoreBand
    {
        $questionnaire = Questionnaire::query()->firstOrCreate(
            ['title' => 'Analytics fixture'],
            ['type' => 'stress', 'version' => 1, 'status' => 'published', 'is_active' => true, 'published_at' => now()],
        );

        return $questionnaire->scoreBands()->create([
            'code' => $code,
            'label' => $label,
            'min_score' => 0,
            'max_score' => 100,
            'position' => $questionnaire->scoreBands()->count() + 1,
            'is_active' => true,
        ]);
    }

    private function assessment(StressScoreBand $band, int $stressScore): StressAssessment
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        return StressAssessment::query()->create([
            'questionnaire_id' => $band->questionnaire_id,
            'student_identity_id' => $student->studentIdentity->id,
            'stress_score_band_id' => $band->id,
            'wellbeing_result_band_id' => $band->id,
            'stress_result_band_id' => $band->id,
            'assessment_status' => 'completed',
            'total_score' => $stressScore,
            'stress_level' => $band->label,
            'overall_percentage' => $stressScore,
            'stress_score' => $stressScore,
            'completed_at' => now(),
        ]);
    }
}
