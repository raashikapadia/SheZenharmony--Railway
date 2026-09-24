<?php

namespace Database\Seeders;

use App\Models\Intervention;
use App\Models\PersonalGuidance;
use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressQuestion;
use App\Models\StressScoreBand;
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
                $question = StressQuestion::query()->updateOrCreate(
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
                    'title' => 'DEMO: Three good moments',
                    'slug' => 'demo-three-good-moments',
                    'description' => 'A light reflection on small positive moments from today.',
                    'content_type' => 'positive_engagement',
                    'instructions' => 'Think of three moments that felt helpful, peaceful, or simply okay today. They can be very small.',
                ],
                [
                    'title' => 'Breathing Challenge',
                    'slug' => 'breathing-challenge',
                    'description' => 'Follow a simple breathing rhythm and take a calm moment.',
                    'content_type' => 'positive_engagement',
                    'instructions' => null,
                ],
                [
                    'title' => 'Gratitude Jar',
                    'slug' => 'gratitude-jar',
                    'description' => 'Write down something positive and add it to your gratitude jar.',
                    'content_type' => 'positive_engagement',
                    'instructions' => null,
                ],
                [
                    'title' => 'Memory Spark',
                    'slug' => 'memory-spark',
                    'description' => 'Gently tap the sparks as they appear and practise noticing the moment.',
                    'content_type' => 'positive_engagement',
                    'instructions' => null,
                ],
                [
                    'title' => 'Mindful Memory',
                    'slug' => 'mindful-memory',
                    'description' => 'Match peaceful symbols and practise your memory mindfully.',
                    'content_type' => 'positive_engagement',
                    'instructions' => null,
                ],
                // Short motivational messages shown under Positive Engagement.
                // The title carries the whole message; admins add, edit and
                // remove these from /admin/positive-engagement.
                [
                    'title' => 'You are doing great — keep going! 🌟',
                    'slug' => 'motivation-keep-going',
                    'description' => null,
                    'content_type' => 'motivation',
                    'instructions' => null,
                ],
                [
                    'title' => 'Small steps still count. You have got this! 💪',
                    'slug' => 'motivation-small-steps',
                    'description' => null,
                    'content_type' => 'motivation',
                    'instructions' => null,
                ],
                [
                    'title' => 'Take a breath, smile, and enjoy the little things today 😊',
                    'slug' => 'motivation-little-things',
                    'description' => null,
                    'content_type' => 'motivation',
                    'instructions' => null,
                ],
                [
                    'title' => 'Today is another chance to do something good for yourself 🌸',
                    'slug' => 'motivation-something-good',
                    'description' => null,
                    'content_type' => 'motivation',
                    'instructions' => null,
                ],
                [
                    'title' => 'Rest is productive too. Give yourself the break you would give a friend ☕',
                    'slug' => 'motivation-rest-is-productive',
                    'description' => null,
                    'content_type' => 'motivation',
                    'instructions' => null,
                ],
                [
                    'title' => 'You have handled hard days before. This one is no different 🌤️',
                    'slug' => 'motivation-handled-before',
                    'description' => null,
                    'content_type' => 'motivation',
                    'instructions' => null,
                ],
            ] as $content) {
                Intervention::query()->updateOrCreate(
                    ['title' => $content['title']],
                    $content + ['is_active' => true, 'created_by_user_id' => $admin->id],
                );
            }

            $this->seedGuidanceToolkit($admin);
        });
    }

    /**
     * Coping strategies for the Personal Guidance toolkit, each matched to the
     * focus areas it suits. Admins can edit, re-match, or remove any of these
     * from /admin/personal-guidance without a release.
     */
    private function seedGuidanceToolkit(User $admin): void
    {
        $sectionId = fn (string $title): ?int => QuestionnaireSection::query()
            ->where('title', 'like', '%'.$title.'%')
            ->value('id');

        $breathing = Intervention::query()->where('content_type', 'breathing')->value('id');

        // Bands where a practical coping strategy is most likely to help.
        $supportBandIds = StressScoreBand::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('code', 'like', '%low%')->orWhere('code', 'like', '%moderate%'))
            ->pluck('id')
            ->all();

        $strategies = [
            [
                'title' => 'Name what needs you first',
                'summary' => 'Feeling pulled in every direction? Pick the one thing that actually needs you right now.',
                'when_it_helps' => 'When everything feels urgent and you are not sure where to start.',
                'steps' => "Write down everything on your mind, unsorted\nCircle the one item with a real deadline today\nPut the rest on a \"later\" list you can close\nStart with that one item for ten minutes",
                'duration_minutes' => 10,
                'content' => 'When a lot is competing for your attention, the pressure often comes from holding it all at once rather than from any single task. Getting it out of your head and choosing one starting point makes the load visible and finite. The rest is still there — it just does not need you this minute.',
                'category' => 'Managing pressure',
                'sections' => ['Personal Growth', 'Environmental'],
                'related' => null,
            ],
            [
                'title' => 'Slow your breathing for two minutes',
                'summary' => 'A short, steady breathing pattern to settle a racing mind.',
                'when_it_helps' => 'When your thoughts feel fast and hard to slow down.',
                'steps' => "Sit somewhere you can be still\nBreathe in gently for four counts\nHold for four\nBreathe out for four, and hold for four\nRepeat four rounds without forcing it",
                'duration_minutes' => 2,
                'content' => 'Lengthening your out-breath is one of the quickest ways to signal to your body that it can ease off. You do not need to clear your mind or feel calm straight away — following the count is enough. If four counts feels long, use three.',
                'category' => 'Grounding',
                'sections' => ['Physical Health'],
                'related' => $breathing,
            ],
            [
                'title' => 'Take the break before you need it',
                'summary' => 'Short, planned pauses hold up better than pushing until you run out.',
                'when_it_helps' => 'During long study or work stretches.',
                'steps' => "Choose a stopping point about 45 minutes away\nWhen you reach it, step away from the screen\nMove, stretch, or get a drink for five minutes\nCome back and pick the next single task",
                'duration_minutes' => 5,
                'content' => 'Breaks taken on purpose tend to be shorter and more restoring than the ones taken when concentration has already gone. Deciding the stopping point in advance also removes the small negotiation of whether you have earned it.',
                'category' => 'Healthy routines',
                'sections' => ['Digital Well-Being', 'Physical Health'],
                'related' => null,
            ],
            [
                'title' => 'Notice the early signs',
                'summary' => 'Catching the first signals of overwhelm gives you more choices.',
                'when_it_helps' => 'When stress tends to build up before you notice it.',
                'steps' => "Think back to a recent stretch that felt heavy\nName one body signal you noticed (tight shoulders, shallow breath)\nName one behaviour change (skipping meals, scrolling late)\nDecide the one small thing you will do next time you spot it",
                'duration_minutes' => 5,
                'content' => 'Overwhelm usually announces itself before it peaks, but the signals are easy to miss while you are busy. Knowing your own two or three early signs turns a vague feeling into something you can respond to sooner, when smaller adjustments still work.',
                'category' => 'Self-awareness',
                'sections' => ['Social Well-Being', 'Support'],
                'related' => null,
            ],
            [
                'title' => 'Make the first step smaller',
                'summary' => 'When starting feels heavy, shrink the step until it feels almost easy.',
                'when_it_helps' => 'When motivation is low and tasks keep getting pushed back.',
                'steps' => "Pick the task you keep postponing\nName the smallest possible first action\nIf it still feels heavy, halve it again\nDo only that, and let stopping there be fine",
                'duration_minutes' => 5,
                'content' => 'Motivation often arrives after starting rather than before it. Making the first step small enough to feel unremarkable lowers the barrier, and finishing something small tends to make the next step easier to reach for.',
                'category' => 'Getting started',
                'sections' => ['Personal Growth', 'Happiness'],
                'related' => null,
            ],
        ];

        foreach ($strategies as $strategy) {
            $guidance = PersonalGuidance::query()->updateOrCreate(
                ['type' => PersonalGuidance::TYPE_GUIDANCE, 'title' => $strategy['title']],
                [
                    'summary' => $strategy['summary'],
                    'when_it_helps' => $strategy['when_it_helps'],
                    'steps' => $strategy['steps'],
                    'duration_minutes' => $strategy['duration_minutes'],
                    'content' => $strategy['content'],
                    'category' => $strategy['category'],
                    'related_intervention_id' => $strategy['related'],
                    'status' => PersonalGuidance::STATUS_PUBLISHED,
                    'created_by_user_id' => $admin->id,
                ],
            );

            $guidance->recommendations()->delete();

            foreach ($strategy['sections'] as $title) {
                $id = $sectionId($title);

                if ($id !== null) {
                    $guidance->recommendations()->create([
                        'questionnaire_section_id' => $id,
                        'is_active' => true,
                    ]);
                }
            }

            // Also match on the check-in result itself, so students still get
            // relevant strategies when a questionnaire produces no per-section
            // breakdown. Practical strategies suit the lower wellbeing bands.
            foreach ($supportBandIds as $bandId) {
                $guidance->recommendations()->create([
                    'stress_score_band_id' => $bandId,
                    'is_active' => true,
                ]);
            }
        }
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
