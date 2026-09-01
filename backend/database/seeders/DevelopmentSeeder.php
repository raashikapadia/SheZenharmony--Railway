<?php

namespace Database\Seeders;

use App\Models\Questionnaire;
use App\Models\Intervention;
use App\Models\User;
use App\Models\WellbeingActivity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DevelopmentSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin.demo@shezen.local';

    public const STUDENT_EMAIL = 'student.demo@student.usp.ac.fj';

    public const QUESTIONNAIRE_TITLE = 'SheZen Development Stress Assessment';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DevelopmentSeeder may only run in the local or testing environment.');
        }

        DB::transaction(function (): void {
            $this->call(RoleSeeder::class);

            $admin = $this->developmentUser(
                self::ADMIN_EMAIL,
                'SheZen Demo Admin',
                'Admin1234!',
                User::ROLE_ADMIN,
            );

            $this->developmentUser(
                self::STUDENT_EMAIL,
                'SheZen Demo Student',
                'Student1234!',
                User::ROLE_STUDENT,
            );

            $questionnaire = Questionnaire::query()->firstOrCreate(
                ['title' => self::QUESTIONNAIRE_TITLE],
                [
                    'description' => 'Development-only assessment for application testing. Not a clinically validated instrument.',
                    'period' => 'Development only',
                    'type' => 'stress',
                    'version' => $this->nextStressVersion(),
                    'status' => 'published',
                    'is_active' => true,
                    'created_by_user_id' => $admin->id,
                    'published_at' => now(),
                ],
            );

            $questionnaire->update([
                'description' => 'Development-only assessment for application testing. Not a clinically validated instrument.',
                'period' => 'Development only',
                'status' => 'published',
                'is_active' => true,
                'published_at' => $questionnaire->published_at ?? now(),
            ]);

            $questions = [
                'dev-stress-overwhelmed' => 'DEMO: I have felt overwhelmed by my current workload.',
                'dev-stress-relax' => 'DEMO: I have found it difficult to relax.',
                'dev-stress-focus' => 'DEMO: Stress has made it difficult for me to focus.',
                'dev-stress-tension' => 'DEMO: I have noticed tension or restlessness in my body.',
                'dev-stress-control' => 'DEMO: I have felt that important things were outside my control.',
            ];
            $memberships = [];

            foreach ($questions as $position => $text) {
                $question = \App\Models\StressQuestion::query()->updateOrCreate(
                    ['code' => $position],
                    [
                        'question_text' => $text,
                        'dimension' => 'development-demo',
                        'help_text' => 'Development-only question; not clinically validated.',
                        'question_type' => 'scale',
                        'position' => count($memberships) + 1,
                        'is_required' => true,
                        'is_active' => true,
                        'is_sensitive' => false,
                        'created_by_user_id' => $admin->id,
                    ],
                );

                foreach ([
                    ['label' => 'Never', 'value' => 'never', 'score' => 0],
                    ['label' => 'Rarely', 'value' => 'rarely', 'score' => 1],
                    ['label' => 'Sometimes', 'value' => 'sometimes', 'score' => 2],
                    ['label' => 'Often', 'value' => 'often', 'score' => 3],
                    ['label' => 'Always', 'value' => 'always', 'score' => 4],
                ] as $optionPosition => $option) {
                    $question->options()->updateOrCreate(
                        ['value' => $option['value']],
                        $option + ['position' => $optionPosition + 1, 'is_active' => true],
                    );
                }

                $memberships[$question->id] = [
                    'position' => count($memberships) + 1,
                    'is_required' => true,
                ];
            }

            $questionnaire->questions()->sync($memberships);

            foreach ([
                ['code' => 'dev-low', 'label' => 'Low (development only)', 'min_score' => 0, 'max_score' => 6, 'position' => 1],
                ['code' => 'dev-moderate', 'label' => 'Moderate (development only)', 'min_score' => 7, 'max_score' => 13, 'position' => 2],
                ['code' => 'dev-high', 'label' => 'High (development only)', 'min_score' => 14, 'max_score' => 20, 'position' => 3],
            ] as $band) {
                $questionnaire->scoreBands()->updateOrCreate(
                    ['code' => $band['code']],
                    $band + ['is_active' => true, 'created_by_user_id' => $admin->id],
                );
            }

            foreach ([
                [
                    'title' => 'DEMO: Box breathing reset',
                    'description' => 'A short visual breathing prompt for a calm pause.',
                    'video_url' => 'https://example.com/shezen-demo/box-breathing',
                    'video_type' => 'youtube',
                    'category' => 'Breathing',
                ],
                [
                    'title' => 'DEMO: Five-senses grounding',
                    'description' => 'A gentle prompt to reconnect with the present moment.',
                    'video_url' => 'https://example.com/shezen-demo/grounding',
                    'video_type' => 'youtube',
                    'category' => 'Grounding',
                ],
            ] as $activity) {
                WellbeingActivity::query()->updateOrCreate(
                    ['title' => $activity['title']],
                    $activity + ['is_active' => true, 'created_by_user_id' => $admin->id],
                );
            }

            foreach ([
                [
                    'title' => 'Box breathing',
                    'slug' => 'box-breathing',
                    'description' => 'A short guided breathing activity.',
                    'content_type' => 'breathing',
                    'instructions' => 'Breathe in gently for 4 counts, hold for 4, breathe out for 4, and hold for 4. Repeat for four comfortable rounds without forcing your breath.',
                ],
                [
                    'title' => 'Gratitude reflection',
                    'slug' => 'gratitude-reflection',
                    'description' => 'Pause and notice three things you appreciate today.',
                    'content_type' => 'journaling',
                    'instructions' => 'Pause and name three things you appreciate today. They can be small: a kind message, a quiet moment, or something you managed well.',
                ],
                [
                    'title' => 'DEMO: A kinder inner voice',
                    'slug' => 'demo-kinder-inner-voice',
                    'description' => 'Pause and replace one harsh thought with a fairer one.',
                    'content_type' => 'affirmation',
                    'instructions' => 'Notice one difficult thought. Ask what you would say to a friend in the same situation, then offer those words to yourself.',
                ],
                [
                    'title' => 'DEMO: Three good moments',
                    'slug' => 'demo-three-good-moments',
                    'description' => 'A light reflection on small positive moments from today.',
                    'content_type' => 'positive_engagement',
                    'instructions' => 'Think of three moments that felt helpful, peaceful, or simply okay today. They can be very small.',
                ],
            ] as $content) {
                Intervention::query()->updateOrCreate(
                    ['title' => $content['title']],
                    $content + ['is_active' => true, 'created_by_user_id' => $admin->id],
                );
            }
        });
    }

    private function developmentUser(string $email, string $name, string $password, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        // Preserve any pre-existing account and, especially, its password.
        // Only an account created by this seeder receives the documented
        // development credentials.
        if (! $user->exists) {
            $user->forceFill([
                'email' => $email,
                'name' => $name,
                'password' => Hash::make($password),
                'role' => $role,
                'account_status' => 'active',
                'email_verified_at' => now(),
            ])->save();
        }

        $user->assignRole($role);

        return $user;
    }

    private function nextStressVersion(): int
    {
        return (int) Questionnaire::query()->where('type', 'stress')->max('version') + 1;
    }
}
