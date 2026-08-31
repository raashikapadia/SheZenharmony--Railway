<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_student_can_view_their_own_profile(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->studentIdentity->profile()->create([
            'date_of_birth' => '2004-03-15',
            'year_of_study' => 'Year 3',
            'country' => 'Fiji',
            'employment_status' => 'Not employed',
            'relationship_status' => 'Single',
            'has_children' => false,
            'living_situation' => 'With family',
        ]);

        $this->actingAs($student)
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.email', $student->email)
            ->assertJsonPath('data.date_of_birth', '2004-03-15')
            ->assertJsonPath('data.age', Carbon::parse('2004-03-15')->age)
            ->assertJsonPath('data.year_of_study', 'Year 3')
            ->assertJsonPath('data.country', 'Fiji')
            ->assertJsonPath('data.living_situation', 'With family');
    }

    public function test_unauthenticated_request_cannot_view_or_edit_profile(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->putJson('/api/v1/profile', ['country' => 'Fiji'])->assertUnauthorized();
    }

    public function test_student_can_update_permitted_fields_and_age_recalculates_from_new_dob(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->studentIdentity->profile()->create(['date_of_birth' => '2004-03-15']);

        $newDob = '2000-06-01';
        $this->actingAs($student)
            ->putJson('/api/v1/profile', [
                'date_of_birth' => $newDob,
                'country' => 'Samoa',
                'year_of_study' => 'Year 4',
                'employment_status' => 'Full-time',
                'relationship_status' => 'Married',
                'has_children' => true,
                'living_situation' => 'Living alone',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Your profile has been updated successfully.')
            ->assertJsonPath('data.date_of_birth', $newDob)
            ->assertJsonPath('data.age', Carbon::parse($newDob)->age)
            ->assertJsonPath('data.country', 'Samoa')
            ->assertJsonPath('data.has_children', true);

        $this->assertDatabaseHas('user_profiles', [
            'student_identity_id' => $student->studentIdentity->id,
            'country' => 'Samoa',
            'year_of_study' => 'Year 4',
            'living_situation' => 'Living alone',
        ]);
    }

    public function test_student_can_change_their_own_email_but_not_to_one_already_in_use(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $student = User::factory()->create(['email' => 'mine@example.com', 'role' => User::ROLE_STUDENT]);
        $student->studentIdentity->profile()->create([]);

        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['email' => 'mine@example.com'])
            ->assertOk();

        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['email' => 'new-address@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'new-address@example.com');
        $this->assertSame('new-address@example.com', $student->fresh()->email);
    }

    public function test_student_cannot_submit_age_role_or_password_through_profile_update(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->studentIdentity->profile()->create(['date_of_birth' => '2004-03-15']);
        $originalHash = $student->password;

        $this->actingAs($student)
            ->putJson('/api/v1/profile', [
                'country' => 'Tonga',
                'age' => 999,
                'role' => User::ROLE_ADMIN,
                'is_admin' => true,
                'password' => 'attempted-new-password',
            ])
            ->assertOk();

        $student->refresh();
        $this->assertTrue($student->isStudent());
        $this->assertFalse($student->isAdmin());
        $this->assertSame($originalHash, $student->password);
        $this->assertNotEquals(999, $student->studentIdentity->profile->age);
        $this->assertSame('Tonga', $student->studentIdentity->profile->country);
    }

    public function test_profile_response_never_exposes_password_hash(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->studentIdentity->profile()->create([]);

        $this->actingAs($student)
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.password_hash');
    }
}
