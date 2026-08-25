<?php

namespace Database\Seeders;

use App\Models\Questionnaire;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DevelopmentSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin.demo@shezen.local';

    public const STUDENT_EMAIL = 'student.demo@shezen.local';

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
