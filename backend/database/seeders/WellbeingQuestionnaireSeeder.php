<?php

namespace Database\Seeders;

use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressQuestion;
use App\Models\User;
use App\Services\QuestionnaireActivationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the supplied SheZen wellbeing instrument: 74 questions across 8
 * weighted categories (the demographic section is intentionally excluded —
 * that information is collected once at registration; spec section 33/34).
 *
 * Idempotent: does nothing once a questionnaire titled
 * "SheZen Wellbeing Questionnaire" exists. When it seeds, it publishes and
 * activates the questionnaire, which archives the "starter" questionnaire
 * via QuestionnaireActivationService.
 *
 * Reverse scoring, stress relevance/direction/weight and any stress result
 * ranges are left at their neutral defaults for an admin to configure — the
 * source instrument does not define a validated methodology for them
 * (spec section 36).
 */
class WellbeingQuestionnaireSeeder extends Seeder
{
    public const TITLE = 'SheZen Wellbeing Questionnaire';

    /** @var array<int, array{title: string, questions: array<int, string>}> */
    private const SECTIONS = [
        [
            'title' => 'Academic and Work-Related Well-Being',
            'questions' => [
                'I often feel overwhelmed by the amount of academic or work-related tasks I need to complete.',
                'Academic or work responsibilities make it difficult for me to relax during my free time.',
                'I feel mentally exhausted after completing academic or work-related activities.',
                'I find it difficult to balance my academic or work responsibilities with my personal life.',
                'I experience anxiety or worry because of deadlines, exams, workloads, or job expectations.',
                'I feel confident in my ability to manage academic or work-related stress.',
                'Stress from school or work affects my sleep quality.',
                'Academic or work pressure negatively affects my mood or emotional well-being.',
                'I feel pressure to perform well in my academic work so I can get a better job.',
                'I feel that the assessments in my academic work are too demanding for my current level.',
            ],
        ],
        [
            'title' => 'Emotional and Psychological Well-Being',
            'questions' => [
                'I generally feel positive and emotionally balanced in my daily life.',
                'I experience frequent stress or anxiety that affects my well-being.',
                'I often feel sad, hopeless, or emotionally low.',
                'I feel confident in myself and my abilities.',
                'I accept myself, including my strengths and weaknesses.',
                'I am able to manage and express my emotions appropriately.',
                'I can calm myself when I feel stressed, worried, or upset.',
                'I am able to cope effectively with difficult situations.',
                'I recover relatively quickly after experiencing setbacks or challenges.',
                'I remain hopeful and continue trying even when I face difficulties.',
            ],
        ],
        [
            'title' => 'Physical Health and Lifestyle Well-Being',
            'questions' => [
                'I ensure that I get enough sleep to prepare for my classes next day.',
                'I ensure to eat regular balanced meals each day.',
                'I engage in regular physical activity or exercise.',
                'I suffer from chronic illness, pain, or disability.',
                'I use alcohol, tobacco or nicotine products, or other substances when I am stressed or to cope up with my academic work.',
                'I get access to appropriate healthcare and mental health support.',
                'I tend to ignore my eating habits due to academic and work stress.',
                'I take supplements to support my health well being and stress.',
            ],
        ],
        [
            'title' => 'Social Well-Being',
            'questions' => [
                'I have positive relationships with my family members.',
                'I can communicate openly with my family about matters that are important to me.',
                'I have friends or peers whom I can trust and rely on for support.',
                'I feel comfortable interacting and spending time with other students.',
                'I feel accepted and included in my academic community.',
                'I rarely experience feelings of loneliness or social isolation.',
                'I am able to resolve disagreements with others in a respectful and constructive way.',
                'I feel satisfied with the quality of my relationships with the people who are important to me.',
            ],
        ],
        [
            'title' => 'Support',
            'questions' => [
                'I receive emotional support from my family when I need it.',
                'I receive help from my family or friends when I face difficulties.',
                'I feel supported by my teachers, colleagues, or supervisors.',
                'I can approach my teachers, colleagues, or supervisors when I need guidance.',
                'My institution provides adequate support for students’ well-being.',
                'I can easily access counselors, therapists, or healthcare providers when needed.',
                'I am aware of the support services and resources available at my institution.',
                'I feel comfortable seeking professional help for personal or emotional difficulties.',
                'I would seek help when needed without worrying about stigma or negative judgment from others.',
            ],
        ],
        [
            'title' => 'Digital Well-Being',
            'questions' => [
                'I spend more time on social media and digital platforms than I intend to.',
                'My use of digital devices sometimes interferes with my academic responsibilities or daily activities.',
                'I compare myself negatively with others based on what I see online.',
                'I worry that I am missing out when I see other people’s activities online.',
                'I have experienced cyberbullying, online harassment, or hurtful behavior online.',
                'My online relationships and communities provide me with meaningful social support.',
                'Online content negatively affects my well-being.',
                'I find it difficult to disconnect from social media or digital devices.',
                'I can set healthy boundaries around when and how long I use digital platforms.',
                'I can protect my privacy and personal information when using digital platforms.',
            ],
        ],
        [
            'title' => 'Environmental and Contextual Well-Being',
            'questions' => [
                'My home environment supports my overall well-being.',
                'My campus or workplace provides a comfortable and supportive environment.',
                'I feel safe and secure in the places where I live, study, or work.',
                'My financial situation allows me to meet my essential needs.',
                'I have access to reliable and affordable transportation.',
                'Noise, crowding, or poor environmental conditions negatively affect my well-being.',
                'I have access to natural or outdoor spaces where I can relax and recharge.',
                'I feel respected and included regardless of my culture, identity, or background.',
                'Discrimination, cultural pressures, or social inequality negatively affect my well-being.',
            ],
        ],
        [
            'title' => 'Personal Growth, Purpose, and Fulfillment',
            'questions' => [
                'I have clear goals that give direction to my life.',
                'My daily activities are consistent with my personal values.',
                'I feel that my life has meaning and purpose.',
                'I am able to make important decisions about my life independently.',
                'I have the freedom to make choices that support my personal growth.',
                'I have a clear and positive understanding of who I am.',
                'I have opportunities to develop skills that are important to me.',
                'Participating in hobbies or creative activities contributes to my fulfillment.',
                'My spiritual or personal beliefs provide me with meaning and guidance.',
                'I feel hopeful and optimistic about my future.',
            ],
        ],
    ];

    /** 1 (Strongly disagree) … 5 (Strongly agree). */
    private const OPTIONS = [
        ['label' => 'Strongly disagree', 'value' => 'strongly_disagree', 'score' => 1],
        ['label' => 'Disagree', 'value' => 'disagree', 'score' => 2],
        ['label' => 'Neutral', 'value' => 'neutral', 'score' => 3],
        ['label' => 'Agree', 'value' => 'agree', 'score' => 4],
        ['label' => 'Strongly agree', 'value' => 'strongly_agree', 'score' => 5],
    ];

    /** The client's fixed result scale; every raw total is converted onto it. */
    private const RESULT_SCALE = ['min' => 0, 'max' => 40];

    /** Spec section 15 — written on the result scale, configurable afterwards via the admin panel. */
    private const OVERALL_BANDS = [
        ['code' => 'low-1', 'label' => 'Low mental well-being', 'min_score' => 0, 'max_score' => 10, 'position' => 1],
        ['code' => 'low-2', 'label' => 'Low mental well-being', 'min_score' => 11, 'max_score' => 20, 'position' => 2],
        ['code' => 'moderate', 'label' => 'Moderate mental well-being', 'min_score' => 21, 'max_score' => 30, 'position' => 3],
        ['code' => 'high', 'label' => 'High mental well-being', 'min_score' => 31, 'max_score' => 40, 'position' => 4],
    ];

    public function run(): void
    {
        if (Questionnaire::query()->where('title', self::TITLE)->exists()) {
            return;
        }

        $adminId = User::query()->where('role', User::ROLE_ADMIN)->value('id');

        $questionnaire = DB::transaction(function () use ($adminId): Questionnaire {
            $questionnaire = Questionnaire::query()->create([
                'title' => self::TITLE,
                'description' => 'A dynamic wellbeing check-in across eight life areas. Demographic details are '
                    .'collected during registration and are not repeated here.',
                'period' => 'Wellbeing',
                'result_scale_min' => self::RESULT_SCALE['min'],
                'result_scale_max' => self::RESULT_SCALE['max'],
                'type' => 'stress',
                'version' => (int) Questionnaire::query()->where('type', 'stress')->max('version') + 1,
                'status' => 'draft',
                'is_active' => false,
                'created_by_user_id' => $adminId,
            ]);

            $position = 0;
            foreach (self::SECTIONS as $sectionIndex => $sectionData) {
                $section = QuestionnaireSection::query()->create([
                    'questionnaire_id' => $questionnaire->id,
                    'title' => $sectionData['title'],
                    'position' => $sectionIndex + 1,
                    'category_weight' => 5,
                    'is_active' => true,
                ]);

                foreach ($sectionData['questions'] as $questionIndex => $text) {
                    $position++;
                    $question = StressQuestion::query()->updateOrCreate(
                        ['code' => sprintf('wb-%d-%d', $sectionIndex + 1, $questionIndex + 1)],
                        [
                            'question_text' => $text,
                            'dimension' => $sectionData['title'],
                            'question_type' => 'scale',
                            'min_score' => 1,
                            'max_score' => 5,
                            'wellbeing_weight' => 1,
                            'is_reverse_scored' => false,
                            'stress_relevant' => false,
                            'stress_weight' => 1,
                            'position' => $position,
                            'is_required' => true,
                            'is_active' => true,
                            'is_sensitive' => false,
                            'created_by_user_id' => $adminId,
                        ],
                    );

                    foreach (self::OPTIONS as $optionIndex => $option) {
                        $question->options()->updateOrCreate(
                            ['value' => $option['value']],
                            $option + ['position' => $optionIndex + 1, 'is_active' => true],
                        );
                    }

                    $questionnaire->questions()->attach($question->id, [
                        'questionnaire_section_id' => $section->id,
                        'position' => $position,
                        'is_required' => true,
                    ]);
                }
            }

            foreach (self::OVERALL_BANDS as $band) {
                $questionnaire->scoreBands()->create($band + [
                    'scope' => 'overall',
                    'is_active' => true,
                    'created_by_user_id' => $adminId,
                ]);
            }

            return $questionnaire;
        });

        app(QuestionnaireActivationService::class)->activate($questionnaire);
    }
}
