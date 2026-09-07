<?php

namespace Tests\Feature;

use App\Models\CategoryResult;
use App\Models\Questionnaire;
use App\Models\StressAssessment;
use App\Models\StressQuestion;
use App\Models\StressResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStudentStressTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_student_stress_scores_by_pseudonymous_id_only(): void
    {
        $student = User::factory()->create([
            'name' => 'Real Student Name',
            'email' => 's90000001@student.usp.ac.fj',
            'role' => User::ROLE_STUDENT,
        ]);
        $identity = $student->studentIdentity;
        [$band] = $this->band('high', 'High', 60, 79);

        $this->assessment($identity->id, $band->id, 61, now()->subDay());
        $this->assessment($identity->id, $band->id, 72, now());

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/student-stress')
            ->assertOk()
            ->assertSee($identity->displayId())
            ->assertSee('High')
            ->assertSee('72')
            ->assertDontSee($student->name)
            ->assertDontSee($student->email)
            ->assertDontSee($student->pseudonymous_uuid);

        $this->actingAs($admin)->get("/admin/student-stress/{$identity->id}")
            ->assertOk()
            ->assertSee($identity->displayId())
            ->assertSee('72 / 100')
            ->assertSee('Assessment History')
            ->assertSeeInOrder(['72', '61'])
            ->assertDontSee($student->name)
            ->assertDontSee($student->email)
            ->assertDontSee($student->pseudonymous_uuid);
    }

    public function test_band_filter_narrows_the_list(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$low] = $this->band('low', 'Low', 0, 29);
        [$high] = $this->band('high', 'High', 60, 79);

        $calm = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->assessment($calm->studentIdentity->id, $low->id, 10, now());
        $stressed = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->assessment($stressed->studentIdentity->id, $high->id, 70, now());

        $this->actingAs($admin)->get('/admin/student-stress?band=high')
            ->assertOk()
            ->assertSee($stressed->studentIdentity->displayId())
            ->assertDontSee($calm->studentIdentity->displayId());
    }

    public function test_students_and_guests_cannot_reach_the_stress_dashboard(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->get('/admin/student-stress')->assertRedirect(route('admin.login'));
        $this->actingAs($student)->get('/admin/student-stress')->assertForbidden();
        $this->actingAs($student)->get('/admin/student-stress/analytics')->assertForbidden();
        $this->actingAs($student)->get("/admin/student-stress/{$student->studentIdentity->id}")->assertForbidden();
    }

    public function test_admin_can_open_a_single_assessment_and_see_every_answer_without_identity(): void
    {
        $student = User::factory()->create([
            'name' => 'Hidden Name', 'email' => 's90000009@student.usp.ac.fj', 'role' => User::ROLE_STUDENT,
        ]);
        [$band, $questionnaire] = $this->band('mid', 'Moderate', 30, 59);
        $assessment = $this->assessment($student->studentIdentity->id, $band->id, 45, now());
        $assessment->update(['questionnaire_id' => $questionnaire->id, 'stress_score' => 42.5]);

        $question = StressQuestion::query()->create([
            'question_text' => 'I have felt tense.', 'question_type' => 'scale', 'is_active' => true,
        ]);
        StressResponse::query()->create([
            'stress_assessment_id' => $assessment->id,
            'stress_question_id' => $question->id,
            'question_text_snapshot' => 'I have felt tense.',
            'option_text_snapshot' => 'Often',
            'score' => 3,
            'scored_value' => 3,
        ]);
        CategoryResult::query()->create([
            'stress_assessment_id' => $assessment->id,
            'section_title_snapshot' => 'Emotional',
            'raw_score' => 3, 'min_possible_score' => 1, 'max_possible_score' => 5,
            'percentage' => 50, 'category_weight' => 5, 'weighted_score' => 2.5,
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('admin.student-stress.assessment', $assessment))
            ->assertOk()
            ->assertSee($student->studentIdentity->displayId())
            ->assertSee('I have felt tense.')
            ->assertSee('Often')
            ->assertSee('Emotional')
            ->assertDontSee('Hidden Name')
            ->assertDontSee($student->email);
    }

    public function test_analytics_page_shows_live_aggregates_and_no_student_identifiers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$low, $questionnaire] = $this->band('low', 'Low', 0, 29);
        [$high] = $this->band('high', 'High', 60, 79);

        $calm = User::factory()->create(['name' => 'Calm Person', 'role' => User::ROLE_STUDENT]);
        $a1 = $this->assessment($calm->studentIdentity->id, $low->id, 10, now());
        $a1->update(['questionnaire_id' => $questionnaire->id, 'wellbeing_result_band_id' => $low->id, 'overall_percentage' => 80]);

        $stressed = User::factory()->create(['name' => 'Stressed Person', 'role' => User::ROLE_STUDENT]);
        $a2 = $this->assessment($stressed->studentIdentity->id, $high->id, 70, now());
        $a2->update(['questionnaire_id' => $questionnaire->id, 'wellbeing_result_band_id' => $high->id, 'overall_percentage' => 30]);

        $this->actingAs($admin)->get(route('admin.student-stress.analytics'))
            ->assertOk()
            ->assertSee('Analytics')
            ->assertSee('Wellbeing result levels')
            ->assertSee('Low')
            ->assertSee('High')
            ->assertSee('55%')                 // avg of 80 and 30
            ->assertSee('2')                   // completed count
            ->assertDontSee('Calm Person')
            ->assertDontSee('Stressed Person');
    }

    private function band(string $code, string $label, int $min, int $max): array
    {
        $questionnaire = Questionnaire::query()->firstOrCreate(
            ['title' => 'Stress fixture'],
            ['type' => 'stress', 'version' => 1, 'status' => 'published', 'is_active' => true, 'published_at' => now()],
        );
        $band = $questionnaire->scoreBands()->firstOrCreate(
            ['code' => $code],
            ['label' => $label, 'min_score' => $min, 'max_score' => $max, 'position' => $min, 'is_active' => true],
        );

        return [$band, $questionnaire];
    }

    private function assessment(int $identityId, int $bandId, int $score, \DateTimeInterface $completedAt): StressAssessment
    {
        return StressAssessment::query()->create([
            'student_identity_id' => $identityId,
            'stress_score_band_id' => $bandId,
            'assessment_status' => 'completed',
            'total_score' => $score,
            'stress_level' => 'recorded',
            'completed_at' => $completedAt,
        ]);
    }
}
