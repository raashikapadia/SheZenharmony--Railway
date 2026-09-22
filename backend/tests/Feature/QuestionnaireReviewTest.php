<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\InterventionRecommendation;
use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressQuestion;
use App\Models\User;
use App\Services\QuestionnaireReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Review & Publish: the whole questionnaire is read and checked, the admin
 * sees plain-language issues with a Fix link, and nothing broken can be
 * published.
 */
class QuestionnaireReviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** A complete, sound questionnaire: 2 sections × 3 questions, 0–40 scale, 4 ranges, one linked support item. */
    private function soundQuestionnaire(User $admin): Questionnaire
    {
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Stress & Wellbeing Check', 'type' => 'stress', 'version' => 1,
            'purpose' => Questionnaire::PURPOSE_REGISTRATION,
            'result_scale_min' => 0, 'result_scale_max' => 40,
        ]);
        foreach (['Emotional Wellbeing', 'Stress & Coping'] as $i => $title) {
            $section = QuestionnaireSection::query()->create([
                'questionnaire_id' => $questionnaire->id, 'title' => $title, 'position' => $i + 1, 'category_weight' => 1, 'is_active' => true,
            ]);
            $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $section]), [
                'questions_text' => "{$title} one\n{$title} two\n{$title} three", 'scale' => 'agree5',
            ]);
        }
        $breathing = Intervention::query()->create(['title' => 'Breathing exercise', 'content_type' => 'journaling', 'is_active' => true]);
        foreach ([['low', 0, 10], ['mild', 11, 20], ['moderate', 21, 30], ['high', 31, 40]] as [$code, $min, $max]) {
            $band = $questionnaire->scoreBands()->create(['code' => $code, 'label' => ucfirst($code), 'min_score' => $min, 'max_score' => $max, 'scope' => 'overall', 'is_active' => true]);
            InterventionRecommendation::query()->create(['stress_score_band_id' => $band->id, 'intervention_id' => $breathing->id, 'priority' => 0, 'is_active' => true]);
        }

        return $questionnaire;
    }

    public function test_a_sound_questionnaire_passes_every_check_and_can_be_published(): void
    {
        $admin = $this->admin();
        $questionnaire = $this->soundQuestionnaire($admin);

        $review = app(QuestionnaireReview::class)->run($questionnaire);
        $this->assertTrue($review['ready']);
        $this->assertSame([], $review['issues']);
        $this->assertSame([], $review['warnings']);
        $this->assertSame(
            ['Questionnaire details', '2 sections', '6 questions', 'Answers configured', 'Scoring configured (raw score 6–30)', 'Result scale: 0–40', '4 result ranges configured', 'Interventions configured'],
            array_column($review['checks'], 'label'),
        );
        $this->assertTrue(collect($review['checks'])->every(fn ($c) => $c['ok']));

        $this->actingAs($admin)->get(route('admin.questionnaires.review', $questionnaire))
            ->assertOk()
            ->assertSee('Everything looks good.')
            ->assertSee('Ready to publish?')
            ->assertSee('2 sections')
            ->assertSee('6 questions')
            ->assertSee('Result scale: 0–40')
            ->assertSee('4 result ranges')
            ->assertSee('1 intervention')
            ->assertDontSee('needs your attention');

        $this->actingAs($admin)->patch(route('admin.questionnaires.publish', $questionnaire))
            ->assertRedirect(route('admin.questionnaires.sections.index', $questionnaire));
        $this->assertTrue((bool) $questionnaire->fresh()->is_active);
        $this->assertSame('published', $questionnaire->fresh()->status);
        // Publishing does not spawn another version.
        $this->assertSame(1, Questionnaire::query()->count());
    }

    public function test_issues_are_listed_in_plain_words_with_a_fix_link_and_block_publishing(): void
    {
        $admin = $this->admin();
        $questionnaire = $this->soundQuestionnaire($admin);
        $section = $questionnaire->sections()->where('title', 'Stress & Coping')->firstOrFail();

        // Break three things in different places.
        $question = $questionnaire->questions()->wherePivot('questionnaire_section_id', $section->id)->orderBy('questionnaire_questions.position')->skip(1)->firstOrFail();
        $question->options()->update(['is_active' => false]);
        $questionnaire->scoreBands()->where('code', 'mild')->update(['min_score' => 5]);
        QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Empty one', 'position' => 3, 'category_weight' => 1, 'is_active' => true,
        ]);

        $review = app(QuestionnaireReview::class)->run($questionnaire->fresh());
        $this->assertFalse($review['ready']);
        $whats = array_column($review['issues'], 'what');
        $this->assertContains('Question 5 needs at least two answer options.', $whats);
        $this->assertContains('"Low" (0–10) and "Mild" (5–20) overlap.', $whats);
        $this->assertContains('This section has no questions. Add a question, or delete the section.', $whats);
        $answerIssue = collect($review['issues'])->firstWhere('what', 'Question 5 needs at least two answer options.');
        $this->assertSame('Section "Stress & Coping"', $answerIssue['where']);
        $this->assertStringContainsString("/questions/{$question->id}/edit", $answerIssue['fix']);

        $page = $this->actingAs($admin)->get(route('admin.questionnaires.review', $questionnaire))->assertOk();
        $page->assertSee('3 things need your attention')
            ->assertSee('Question 5 needs at least two answer options.')
            ->assertSee('Please fix the 3 items above before publishing.')
            ->assertSee('disabled', false)
            ->assertDontSee('Ready to publish?');

        // The gate holds even for a direct request.
        $this->actingAs($admin)->patch(route('admin.questionnaires.publish', $questionnaire))
            ->assertRedirect(route('admin.questionnaires.review', $questionnaire));
        $this->assertFalse((bool) $questionnaire->fresh()->is_active);

        // The editor's banner says the same thing.
        $this->actingAs($admin)->get(route('admin.questionnaires.sections.index', $questionnaire))
            ->assertOk()
            ->assertSee('2 to fix')
            ->assertSee('1 to fix')
            ->assertSee('Review &amp; publish', false);
    }

    public function test_possible_duplicates_are_pointed_out_but_do_not_block(): void
    {
        $admin = $this->admin();
        $questionnaire = $this->soundQuestionnaire($admin);
        $sections = $questionnaire->sections()->orderBy('position')->get();

        // The same question added to both sections, and a repeated section name.
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $sections[0]]), [
            'questions_text' => 'How often do you feel stressed?', 'scale' => 'agree5',
        ]);
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $sections[1]]), [
            'questions_text' => 'how often do you feel  stressed? ', 'scale' => 'agree5',
        ]);
        $sections[1]->update(['title' => 'emotional wellbeing']);

        $review = app(QuestionnaireReview::class)->run($questionnaire->fresh());
        $this->assertTrue($review['ready']);
        $this->assertSame(0, $questionnaire->questions()->count() - 8);
        $whats = array_column($review['warnings'], 'what');
        $this->assertContains('"How often do you feel stressed?" appears 2 times.', $whats);
        $this->assertContains('Possible duplicate section: "Emotional Wellbeing" appears 2 times.', $whats);
        $duplicate = collect($review['warnings'])->firstWhere('where', 'Possible duplicate question');
        $this->assertSame('Appears in: Section 1 — Emotional Wellbeing · Section 2 — emotional wellbeing', $duplicate['detail']);

        $this->actingAs($admin)->get(route('admin.questionnaires.review', $questionnaire))
            ->assertOk()
            ->assertSee('2 things worth a look')
            ->assertSee('Possible duplicate question')
            ->assertSee('Everything looks good.');
    }

    public function test_ranges_without_support_and_broken_links_are_flagged(): void
    {
        $admin = $this->admin();
        $questionnaire = $this->soundQuestionnaire($admin);
        Intervention::query()->update(['is_active' => false]);

        $review = app(QuestionnaireReview::class)->run($questionnaire->fresh());
        $this->assertTrue($review['ready']);
        $this->assertFalse(collect($review['checks'])->firstWhere('label', 'Interventions configured')['ok']);
        $whats = array_column($review['warnings'], 'what');
        $this->assertContains('"Low" points to a support item that no longer exists or is switched off.', $whats);
        $this->assertContains('"Low" has no support item to recommend.', $whats);
    }

    public function test_a_question_outside_any_section_and_a_missing_scale_are_issues(): void
    {
        $admin = $this->admin();
        $questionnaire = $this->soundQuestionnaire($admin);
        $questionnaire->update(['result_scale_min' => null, 'result_scale_max' => null]);
        $stray = StressQuestion::query()->create(['question_text' => 'Stray', 'question_type' => 'scale', 'is_active' => true]);
        $stray->options()->createMany([
            ['label' => 'No', 'value' => 'no', 'score' => 1, 'position' => 1, 'is_active' => true],
            ['label' => 'Yes', 'value' => 'yes', 'score' => 5, 'position' => 2, 'is_active' => true],
        ]);
        $questionnaire->questions()->attach($stray->id, ['position' => 99, 'is_required' => true]);

        $review = app(QuestionnaireReview::class)->run($questionnaire->fresh());
        $whats = array_column($review['issues'], 'what');
        $this->assertContains('"Stray" is not inside a section. Every question needs one.', $whats);
        $this->assertContains("Enter the client's result scale (its minimum and maximum) so results can be reported on it.", $whats);
        $this->assertFalse($review['ready']);
    }

    public function test_a_scheduled_go_live_is_stated_plainly_and_hidden_from_the_app_until_then(): void
    {
        $admin = $this->admin();
        $questionnaire = $this->soundQuestionnaire($admin);
        $questionnaire->update(['published_at' => now()->addDays(10)->setTime(15, 42)]);
        $when = $questionnaire->published_at->format('j F Y \a\t g:ia');

        // Before publishing, the review says when it will open.
        $this->actingAs($admin)->get(route('admin.questionnaires.review', $questionnaire))
            ->assertOk()
            ->assertSee('students will see it from <strong>'.$when.'</strong>', false);

        $this->actingAs($admin)->patch(route('admin.questionnaires.publish', $questionnaire))->assertRedirect();
        $questionnaire->refresh();
        $this->assertTrue($questionnaire->isScheduled());
        $this->assertSame('Scheduled', $questionnaire->publishState());

        $this->actingAs($admin)->get(route('admin.questionnaires.review', $questionnaire))
            ->assertOk()
            ->assertSee('scheduled to open on '.$when, false);
        $this->actingAs($admin)->get(route('admin.questionnaires.index'))
            ->assertOk()
            ->assertSee('Scheduled');

        // The app is told to come back later rather than handed a
        // questionnaire it cannot submit.
        $this->getJson('/api/v1/questionnaires/active')
            ->assertNotFound()
            ->assertJsonPath('message', 'The next check-in opens on '.$questionnaire->published_at->format('j F Y').' at '.$questionnaire->published_at->format('g:ia').'. Please come back then.');

        // Clearing the date opens it immediately.
        $questionnaire->update(['published_at' => now()->subMinute()]);
        $this->getJson('/api/v1/questionnaires/active')->assertOk()->assertJsonPath('data.id', $questionnaire->id);
        $this->assertSame('Live', $questionnaire->fresh()->publishState());
    }
}
