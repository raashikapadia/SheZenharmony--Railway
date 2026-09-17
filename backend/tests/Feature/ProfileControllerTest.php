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
            ->assertJsonPath('data.shezen_id', $student->studentIdentity->displayId())
            ->assertJsonMissingPath('data.email')
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

    public function test_date_of_birth_must_make_the_student_at_least_eighteen_today(): void
    {
        $this->travelTo(Carbon::parse('2026-09-18 10:00:00'));
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->studentIdentity->profile()->create(['date_of_birth' => '2004-03-15']);
        $tooYoung = 'You must be 18 years or older to participate.';

        // Born in the cut-off year but the birthday is still to come.
        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['date_of_birth' => '2008-09-19'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_of_birth.0', $tooYoung);
        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['date_of_birth' => '2009-03-15'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_of_birth.0', $tooYoung);
        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['date_of_birth' => '2027-01-01'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_of_birth.0', 'Date of birth cannot be in the future.');
        $this->assertSame('2004-03-15', $student->studentIdentity->profile->fresh()->date_of_birth->toDateString());

        // Eighteen today, and a 2008 birthday that has already passed.
        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['date_of_birth' => '2008-09-18'])
            ->assertOk()
            ->assertJsonPath('data.age', 18);
        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['date_of_birth' => '2008-01-01'])
            ->assertOk()
            ->assertJsonPath('data.date_of_birth', '2008-01-01');
    }

    public function test_year_of_study_must_be_an_offered_option_and_other_needs_a_specification(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->studentIdentity->profile()->create(['year_of_study' => 'Year 3']);

        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['year_of_study' => 'Year 9'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year_of_study');

        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['year_of_study' => 'Other'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.year_of_study_detail.0', 'Please specify your year of study.');
        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['year_of_study' => 'Other', 'year_of_study_detail' => '  '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year_of_study_detail');
        $this->assertSame('Year 3', $student->studentIdentity->profile->fresh()->year_of_study);

        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['year_of_study' => 'Other', 'year_of_study_detail' => 'Part-time diploma'])
            ->assertOk()
            ->assertJsonPath('data.year_of_study', 'Other')
            ->assertJsonPath('data.year_of_study_detail', 'Part-time diploma');
        $this->assertSame('Other (Part-time diploma)', $student->studentIdentity->profile->fresh()->yearOfStudyDescription());

        // Moving off "Other" drops the specification rather than keeping a stale one.
        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['year_of_study' => 'Year 2', 'year_of_study_detail' => 'Part-time diploma'])
            ->assertOk()
            ->assertJsonPath('data.year_of_study', 'Year 2')
            ->assertJsonPath('data.year_of_study_detail', null);
        $this->assertDatabaseHas('user_profiles', [
            'student_identity_id' => $student->studentIdentity->id,
            'year_of_study' => 'Year 2',
            'year_of_study_detail' => null,
        ]);
    }

    public function test_shezen_id_is_short_readable_unique_and_stable(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $student->studentIdentity->profile()->create([]);

        $first = $this->actingAs($student)->getJson('/api/v1/profile')->assertOk()->json('data.shezen_id');
        $second = $this->actingAs($student)->getJson('/api/v1/profile')->assertOk()->json('data.shezen_id');

        // "SZ" plus five characters from an alphabet with no 0/O or 1/I/L.
        $this->assertMatchesRegularExpression('/^SZ[A-HJ-NP-Z2-9]{5}$/', $first);
        $this->assertSame($first, $second);
        $this->assertSame($first, $student->studentIdentity->fresh()->shezen_code);
        $this->assertNotSame($first, $other->studentIdentity->displayId());
    }

    public function test_profile_update_cannot_change_or_return_authentication_email(): void
    {
        $student = User::factory()->create(['email' => 'mine@example.com', 'role' => User::ROLE_STUDENT]);
        $student->studentIdentity->profile()->create([]);

        $this->actingAs($student)
            ->putJson('/api/v1/profile', ['email' => 'new-address@example.com'])
            ->assertOk()
            ->assertJsonMissingPath('data.email');

        $this->assertSame('mine@example.com', $student->fresh()->email);
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
        $this->actingAs($student)
            ->getJson('/api/v1/profile')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.id');
    }
}
