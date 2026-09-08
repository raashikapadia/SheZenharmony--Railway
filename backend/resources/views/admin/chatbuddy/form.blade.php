@extends('layouts.admin')
@section('title', $intent->exists ? 'Edit Shezen category' : 'New Shezen category')
@section('body')
<main class="content">
    <section class="panel stack-sm">
        <a class="backlink" href="{{ route('admin.chatbuddy.index') }}">&larr; Shezen Chat Buddy</a>
        <h1>{{ $intent->exists ? 'Edit category' : 'New category' }}</h1>

        @if($errors->any())
            <div class="panel" style="border-color:#c0392b">
                <ul class="muted">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $intent->exists ? route('admin.chatbuddy.update', $intent) : route('admin.chatbuddy.store') }}">
            @csrf
            @if($intent->exists) @method('PUT') @endif

            <div class="field-row">
                <div>
                    <label for="name">Category name</label>
                    <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $intent->name) }}" placeholder="e.g. Feeling stressed">
                </div>
                <div>
                    <label for="code">Reference code</label>
                    <input id="code" name="code" type="text" required maxlength="60" value="{{ old('code', $intent->code) }}" placeholder="e.g. stressed">
                    <p class="muted" style="font-size:.8rem">Lower-case letters, numbers and underscores. Used to link buttons together.</p>
                </div>
            </div>

            <div style="margin-top:12px">
                <label for="keywords">Keywords that select this category</label>
                <textarea id="keywords" name="keywords" rows="3" maxlength="4000" placeholder="stress, stressed, pressure, worried">{{ old('keywords', $intent->keywords) }}</textarea>
                <p class="muted" style="font-size:.8rem">Separate with commas or new lines. Matched as whole words, so "sad" will not fire inside "Saturday".</p>
            </div>

            <div style="margin-top:12px">
                <label for="message">What Shezen replies</label>
                <textarea id="message" name="message" rows="4" required maxlength="2000" placeholder="Sounds like things are feeling a bit heavy right now. Want to take a quick breather with me?">{{ old('message', $message) }}</textarea>
                <p class="muted" style="font-size:.8rem">Write it the way a kind friend would say it. Avoid clinical wording, and never state or imply a diagnosis.</p>
            </div>

            <div style="margin-top:12px">
                <label for="description">Internal note <span class="muted">(optional, not shown to students)</span></label>
                <input id="description" name="description" type="text" maxlength="2000" value="{{ old('description', $intent->description) }}">
            </div>

            <h2 style="margin-top:26px">Buttons offered after this reply</h2>
            <p class="muted" style="font-size:.8rem">Each button either continues the conversation in another category, or opens a section of the app. Leave a label empty to skip that row.</p>

            @php $existing = old('replies', $quickReplies->map(fn ($reply) => [
                'label' => $reply->label,
                'links_to' => $reply->links_to,
                'next_chat_intent_id' => $reply->next_chat_intent_id,
            ])->all()); @endphp

            @for($i = 0; $i < 6; $i++)
                @php $row = $existing[$i] ?? ['label' => '', 'links_to' => '', 'next_chat_intent_id' => '']; @endphp
                <div class="field-row" style="margin-top:8px">
                    <div>
                        <label for="reply-label-{{ $i }}">Button {{ $i + 1 }} label</label>
                        <input id="reply-label-{{ $i }}" name="replies[{{ $i }}][label]" type="text" maxlength="120" value="{{ $row['label'] }}" placeholder="e.g. Take a breathing break">
                    </div>
                    <div>
                        <label for="reply-link-{{ $i }}">Opens this section</label>
                        <select id="reply-link-{{ $i }}" name="replies[{{ $i }}][links_to]">
                            <option value="">— none —</option>
                            @foreach($linkTargets as $value => $label)
                                <option value="{{ $value }}" @selected($row['links_to'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="reply-next-{{ $i }}">Or continues to</label>
                        <select id="reply-next-{{ $i }}" name="replies[{{ $i }}][next_chat_intent_id]">
                            <option value="">— none —</option>
                            @foreach($intents as $option)
                                <option value="{{ $option->id }}" @selected((int) $row['next_chat_intent_id'] === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endfor

            <h2 style="margin-top:26px">Behaviour</h2>
            <div class="field-row" style="margin-top:12px">
                <div>
                    <label for="priority">Priority</label>
                    <input id="priority" name="priority" type="number" min="1" max="9999" required value="{{ old('priority', $intent->priority ?? 100) }}">
                    <p class="muted" style="font-size:.8rem">Lower numbers are checked first when several categories match.</p>
                </div>
                <div>
                    <label>Options</label>
                    <label style="display:block;font-weight:400;margin:4px 0">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $intent->is_active ?? true))>
                        Active
                    </label>
                    <label style="display:block;font-weight:400;margin:4px 0">
                        <input type="hidden" name="is_starter" value="0">
                        <input type="checkbox" name="is_starter" value="1" @checked(old('is_starter', $intent->is_starter))>
                        Offer as a conversation starter
                    </label>
                    <label style="display:block;font-weight:400;margin:4px 0">
                        <input type="hidden" name="is_crisis" value="0">
                        <input type="checkbox" name="is_crisis" value="1" @checked(old('is_crisis', $intent->is_crisis))>
                        Safety category
                    </label>
                    <p class="muted" style="font-size:.8rem">Safety categories are checked before every other rule, so a concerning message always reaches this reply first.</p>
                </div>
            </div>

            <div class="actions" style="margin-top:18px">
                <button class="button" type="submit">Save category</button>
                <a class="button button-secondary" href="{{ route('admin.chatbuddy.index') }}">Cancel</a>
            </div>
        </form>
    </section>
</main>
@endsection
