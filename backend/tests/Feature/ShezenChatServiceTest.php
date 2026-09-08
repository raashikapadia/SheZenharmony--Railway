<?php

namespace Tests\Feature;

use App\Models\ChatIntent;
use App\Models\ChatResponse;
use App\Services\ShezenChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shezen's replies must be deterministic and admin-authored. These tests pin
 * the matching rules, and in particular that a concerning message can never
 * fall through to an ordinary reply.
 */
class ShezenChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private function intent(array $attributes, string $message, bool $active = true): ChatIntent
    {
        $intent = ChatIntent::query()->create($attributes + [
            'name' => $attributes['code'],
            'is_active' => $active,
        ]);

        ChatResponse::query()->create([
            'chat_intent_id' => $intent->id,
            'message' => $message,
            'priority' => 100,
            'is_active' => true,
        ]);

        return $intent;
    }

    public function test_a_message_matches_the_intent_that_owns_its_keyword(): void
    {
        $this->intent(['code' => 'stressed', 'keywords' => 'stress, stressed', 'priority' => 20], 'Sounds heavy.');

        $result = app(ShezenChatService::class)->reply('I am so stressed today');

        $this->assertSame('stressed', $result['intent']?->code);
        $this->assertSame('Sounds heavy.', $result['message']);
        $this->assertFalse($result['is_crisis']);
    }

    public function test_a_crisis_intent_wins_even_when_another_intent_has_a_better_priority(): void
    {
        $this->intent(['code' => 'tired', 'keywords' => 'tired', 'priority' => 1], 'Rest counts.');
        $this->intent(
            ['code' => 'crisis', 'keywords' => 'hurt myself', 'priority' => 900, 'is_crisis' => true],
            'Please reach out to someone you trust.',
        );

        $result = app(ShezenChatService::class)->reply('I am tired and I want to hurt myself');

        $this->assertSame('crisis', $result['intent']?->code);
        $this->assertTrue($result['is_crisis']);
    }

    public function test_keywords_match_on_word_boundaries_only(): void
    {
        $this->intent(['code' => 'sad', 'keywords' => 'sad', 'priority' => 10], 'Sorry it is low.');

        $service = app(ShezenChatService::class);

        $this->assertNull($service->match('See you on Saturday'));
        $this->assertSame('sad', $service->match('feeling sad')?->code);
    }

    public function test_an_inactive_intent_is_never_matched(): void
    {
        $this->intent(['code' => 'sad', 'keywords' => 'sad', 'priority' => 10], 'Sorry it is low.', active: false);

        $this->assertNull(app(ShezenChatService::class)->match('I feel sad'));
    }

    public function test_an_unmatched_message_falls_back_without_inventing_a_reply(): void
    {
        $this->intent(['code' => 'sad', 'keywords' => 'sad', 'priority' => 10], 'Sorry it is low.');

        $result = app(ShezenChatService::class)->reply('quantum chromodynamics');

        $this->assertNull($result['intent']);
        $this->assertStringContainsString('not sure I follow', $result['message']);
        $this->assertSame([], $result['quick_replies']);
    }

    public function test_the_same_message_always_resolves_to_the_same_reply(): void
    {
        $this->intent(['code' => 'a', 'keywords' => 'help', 'priority' => 10], 'First.');
        $this->intent(['code' => 'b', 'keywords' => 'help', 'priority' => 20], 'Second.');

        $service = app(ShezenChatService::class);

        $this->assertSame('First.', $service->reply('help')['message']);
        $this->assertSame('First.', $service->reply('help')['message']);
    }

    public function test_quick_replies_carry_their_link_target(): void
    {
        $intent = $this->intent(['code' => 'relax', 'keywords' => 'relax', 'priority' => 10], 'Slow down.');
        $intent->responses()->first()->quickReplies()->create([
            'label' => 'Take a breathing break',
            'links_to' => 'wellbeing_activities',
            'position' => 1,
        ]);

        $replies = app(ShezenChatService::class)->reply('relax')['quick_replies'];

        $this->assertCount(1, $replies);
        $this->assertSame('wellbeing_activities', $replies[0]['links_to']);
    }

    public function test_starters_only_include_intents_marked_as_starters(): void
    {
        $this->intent(['code' => 'greeting', 'keywords' => 'hi', 'priority' => 10, 'is_starter' => true], 'Hi.');
        $this->intent(['code' => 'goodbye', 'keywords' => 'bye', 'priority' => 20], 'Bye.');

        $starters = app(ShezenChatService::class)->starters();

        $this->assertCount(1, $starters);
        $this->assertSame('greeting', $starters->first()->code);
    }
}
