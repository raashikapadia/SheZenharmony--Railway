<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Questionnaire;
use App\Models\User;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_complete_repeatable_development_data(): void
    {
        $this->seed(DevelopmentSeeder::class);
        $this->seed(DevelopmentSeeder::class);

        $admin = User::query()->where('email', DevelopmentSeeder::ADMIN_EMAIL)->sole();
        $student = User::query()->where('email', DevelopmentSeeder::STUDENT_EMAIL)->sole();
        $questionnaire = Questionnaire::query()
            ->where('title', DevelopmentSeeder::QUESTIONNAIRE_TITLE)
            ->with(['questions.options', 'scoreBands'])
            ->sole();

        $this->assertTrue(Hash::check('Admin1234!', $admin->password));
        $this->assertTrue(Hash::check('Student1234!', $student->password));
        $this->assertTrue($admin->hasRole(User::ROLE_ADMIN));
        $this->assertTrue($student->hasRole(User::ROLE_STUDENT));
        $this->assertSame('active', $admin->account_status);
        $this->assertSame('active', $student->account_status);
        $this->assertNotNull($student->pseudonymous_uuid);

        $this->assertSame('published', $questionnaire->status);
        $this->assertTrue($questionnaire->is_active);
        $this->assertCount(5, $questionnaire->questions);
        $this->assertSame(25, $questionnaire->questions->sum(fn ($question) => $question->options->count()));
        $this->assertTrue($questionnaire->questions->every(fn ($question) => $question->pivot->is_required));

        $bands = $questionnaire->scoreBands->sortBy('min_score')->values();
        $this->assertSame([[0, 6], [7, 13], [14, 20]], $bands
            ->map(fn ($band) => [$band->min_score, $band->max_score])->all());

        $this->assertSame(1, User::query()->where('email', DevelopmentSeeder::ADMIN_EMAIL)->count());
        $this->assertSame(1, User::query()->where('email', DevelopmentSeeder::STUDENT_EMAIL)->count());
        $this->assertSame(1, Questionnaire::query()->where('title', DevelopmentSeeder::QUESTIONNAIRE_TITLE)->count());
        $this->assertFalse(Intervention::query()->where('content_type', 'affirmation')->exists());
    }
}
