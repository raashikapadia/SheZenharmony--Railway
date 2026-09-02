<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\StressAssessment;
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
        $this->actingAs($student)->get("/admin/student-stress/{$student->studentIdentity->id}")->assertForbidden();
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
