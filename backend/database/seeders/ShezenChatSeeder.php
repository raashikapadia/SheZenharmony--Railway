<?php

namespace Database\Seeders;

use App\Models\ChatIntent;
use App\Models\ChatResponse;
use Illuminate\Database\Seeder;

/**
 * Shezen's starter script.
 *
 * A small, deliberately plain-spoken set of categories and replies so the
 * feature has something real behind it on a fresh database. Every line is
 * ordinary written copy, editable from /admin/chatbuddy — administrators are
 * expected to rewrite these in the university's own voice.
 *
 * Guarded: does nothing once any intent exists, so it never overwrites content
 * an administrator has already written.
 */
class ShezenChatSeeder extends Seeder
{
    public function run(): void
    {
        if (ChatIntent::query()->exists()) {
            return;
        }

        // Crisis first. Priority 1 and is_crisis both ensure this is checked
        // before every other rule.
        $this->intent(
            code: 'crisis',
            name: 'Needs urgent support',
            keywords: 'suicide, kill myself, end my life, self harm, hurt myself, want to die, no reason to live',
            message: "I'm really sorry you're going through something this difficult. You don't have to handle it alone. Please consider reaching out to someone you trust, or to a support service near you, so you can talk to a real person about this.",
            isCrisis: true,
            priority: 1,
            replies: [
                ['label' => 'Show me a calming activity', 'links_to' => 'wellbeing_activities'],
            ],
            description: 'Prewritten supportive response for concerning messages. Never offers advice or a diagnosis.',
        );

        $this->intent(
            code: 'greeting',
            name: 'Greeting',
            keywords: 'hi, hello, hey, good morning, good evening, morning',
            message: "Hi, I'm Shezen. Good to see you. How are you feeling today?",
            isStarter: true,
            priority: 10,
            replies: [
                ['label' => 'Pretty good', 'next' => 'feeling_good'],
                ['label' => 'Just okay', 'next' => 'check_in'],
                ['label' => 'Not great', 'next' => 'stressed'],
            ],
        );

        $this->intent(
            code: 'stressed',
            name: 'Feeling stressed',
            keywords: 'stress, stressed, stressful, pressure, anxious, worried, panic',
            message: "Sounds like things are feeling a bit heavy right now. Let's keep it simple — what sounds best?",
            isStarter: true,
            priority: 20,
            replies: [
                ['label' => 'Take a breathing break', 'links_to' => 'wellbeing_activities'],
                ['label' => 'Give me a quick tip', 'links_to' => 'personal_guidance'],
                ['label' => 'Something distracting', 'links_to' => 'games'],
                ['label' => 'Tell me more', 'next' => 'tell_me_more'],
            ],
        );

        $this->intent(
            code: 'overwhelmed',
            name: 'Feeling overwhelmed',
            keywords: 'overwhelmed, too much, cant cope, can not cope, drowning, behind, deadline',
            message: "That's a lot to be holding at once. You don't have to sort all of it right now — just the next small piece.",
            priority: 25,
            replies: [
                ['label' => 'Help me pick one thing', 'links_to' => 'personal_guidance'],
                ['label' => 'I need a pause', 'links_to' => 'wellbeing_activities'],
            ],
        );

        $this->intent(
            code: 'sad',
            name: 'Feeling low',
            keywords: 'sad, down, low, unhappy, crying, upset',
            message: "I'm sorry it's feeling low right now. That's a real thing to sit with, and it's okay to take it gently today.",
            priority: 30,
            replies: [
                ['label' => 'Something kind to read', 'links_to' => 'positive_engagement'],
                ['label' => 'A small thing to try', 'links_to' => 'personal_guidance'],
            ],
        );

        $this->intent(
            code: 'tired',
            name: 'Feeling tired',
            keywords: 'tired, exhausted, no energy, cant sleep, sleepy, burnt out, burned out',
            message: "Running low on energy makes everything harder. Rest counts as doing something, even when it doesn't feel like it.",
            isStarter: true,
            priority: 35,
            replies: [
                ['label' => 'Something calming', 'links_to' => 'wellbeing_activities'],
                ['label' => 'A gentle reminder', 'links_to' => 'positive_engagement'],
            ],
        );

        $this->intent(
            code: 'lonely',
            name: 'Feeling lonely',
            keywords: 'lonely, alone, no one, nobody, isolated, left out',
            message: "Feeling on your own is hard, and saying it out loud takes something. I'm glad you did.",
            priority: 40,
            replies: [
                ['label' => 'Something to lift the mood', 'links_to' => 'positive_engagement'],
                ['label' => 'A small step I can take', 'links_to' => 'personal_guidance'],
            ],
        );

        $this->intent(
            code: 'unmotivated',
            name: 'Low motivation',
            keywords: 'unmotivated, no motivation, cant start, procrastinating, lazy, stuck',
            message: "Starting is usually the hardest part. Making the first step smaller often works better than pushing harder.",
            priority: 45,
            replies: [
                ['label' => 'Show me how', 'links_to' => 'personal_guidance'],
                ['label' => 'I need a break first', 'links_to' => 'games'],
            ],
        );

        $this->intent(
            code: 'encouragement',
            name: 'Wants encouragement',
            keywords: 'encourage, encouragement, cheer me up, motivate me, need a boost',
            message: "Here's something to take with you today.",
            isStarter: true,
            priority: 50,
            replies: [
                ['label' => 'Read a message', 'links_to' => 'positive_engagement'],
            ],
        );

        $this->intent(
            code: 'relax',
            name: 'Wants to relax',
            keywords: 'relax, calm down, breathe, breathing, unwind, ground me',
            message: "Let's slow things down a little. A couple of minutes is enough to make a difference.",
            priority: 55,
            replies: [
                ['label' => 'Take a breathing break', 'links_to' => 'wellbeing_activities'],
            ],
        );

        $this->intent(
            code: 'distraction',
            name: 'Wants a distraction',
            keywords: 'distract, distraction, bored, take my mind off, something fun, game',
            message: "A short break somewhere else can help. Want something light?",
            priority: 60,
            replies: [
                ['label' => 'Play something', 'links_to' => 'games'],
                ['label' => 'Try a quick quiz', 'links_to' => 'positive_engagement'],
            ],
        );

        $this->intent(
            code: 'check_in',
            name: 'General check-in',
            keywords: 'okay, alright, fine, so so, not sure, meh',
            message: "Okay is a perfectly fine place to be. Is there anything you'd like to do with the next few minutes?",
            priority: 65,
            replies: [
                ['label' => 'Something calming', 'links_to' => 'wellbeing_activities'],
                ['label' => 'Something uplifting', 'links_to' => 'positive_engagement'],
                ['label' => 'Some guidance', 'links_to' => 'personal_guidance'],
            ],
        );

        $this->intent(
            code: 'feeling_good',
            name: 'Feeling good',
            keywords: 'good, great, happy, better, well, fine thanks',
            message: "That's good to hear. Worth noticing the days that feel steadier — they count too.",
            priority: 70,
            replies: [
                ['label' => 'Keep it going', 'links_to' => 'positive_engagement'],
            ],
        );

        $this->intent(
            code: 'tell_me_more',
            name: 'Wants to talk more',
            keywords: 'tell me more, talk about it, explain, more',
            message: "I can't hold a full conversation yet, but writing down what's on your mind often helps on its own. Your guidance section has a few ways in.",
            priority: 75,
            replies: [
                ['label' => 'Open my guidance', 'links_to' => 'personal_guidance'],
            ],
        );

        $this->intent(
            code: 'goodbye',
            name: 'Goodbye',
            keywords: 'bye, goodbye, see you, thanks, thank you, later',
            message: "Take care of yourself. I'll be here whenever you want to talk.",
            priority: 80,
            replies: [],
        );
    }

    /**
     * @param  array<int, array<string, string>>  $replies
     */
    private function intent(
        string $code,
        string $name,
        string $keywords,
        string $message,
        array $replies,
        int $priority,
        bool $isCrisis = false,
        bool $isStarter = false,
        ?string $description = null,
    ): void {
        $intent = ChatIntent::query()->create([
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'keywords' => $keywords,
            'is_crisis' => $isCrisis,
            'is_starter' => $isStarter,
            'priority' => $priority,
            'is_active' => true,
        ]);

        $response = ChatResponse::query()->create([
            'chat_intent_id' => $intent->id,
            'message' => $message,
            'priority' => 100,
            'is_active' => true,
        ]);

        foreach ($replies as $position => $reply) {
            $response->quickReplies()->create([
                'label' => $reply['label'],
                'links_to' => $reply['links_to'] ?? null,
                'position' => $position + 1,
                // Resolved after every intent exists, below.
                'next_chat_intent_id' => null,
            ]);
        }

        // Remember the intended target so links can be resolved once all the
        // intents have been created.
        foreach ($replies as $position => $reply) {
            if (! isset($reply['next'])) {
                continue;
            }

            $this->pendingLinks[] = [
                'response_id' => $response->id,
                'position' => $position + 1,
                'code' => $reply['next'],
            ];
        }

        $this->resolvePendingLinks();
    }

    /** @var array<int, array{response_id: int, position: int, code: string}> */
    private array $pendingLinks = [];

    /** Links a quick reply to its target intent once that intent exists. */
    private function resolvePendingLinks(): void
    {
        foreach ($this->pendingLinks as $index => $link) {
            $target = ChatIntent::query()->where('code', $link['code'])->first();

            if ($target === null) {
                continue;
            }

            ChatResponse::query()
                ->find($link['response_id'])
                ?->quickReplies()
                ->where('position', $link['position'])
                ->update(['next_chat_intent_id' => $target->id]);

            unset($this->pendingLinks[$index]);
        }
    }
}
