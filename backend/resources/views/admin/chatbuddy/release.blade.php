@extends('layouts.admin')
@section('title', 'Chat Buddy Manager')
@section('body')
<style>
    .chatbuddy-manager{--cb-ink:#172f55;--cb-muted:#5d7191;--cb-blue:#2d78bc;--cb-soft:#eaf3fd;--cb-line:#d7e4f3}
    .chatbuddy-manager .panel{border:1px solid var(--cb-line);border-radius:17px;box-shadow:0 8px 24px rgba(39,83,129,.06);background:#fff}
    .chatbuddy-manager h1,.chatbuddy-manager h2{color:var(--cb-ink)}
    .chatbuddy-header{align-items:center;border-bottom:1px solid var(--cb-line);padding-bottom:18px}
    .chatbuddy-header h1{margin-bottom:5px;font-size:clamp(1.65rem,2.4vw,2.15rem);letter-spacing:-.03em}
    .chatbuddy-header .lede{margin:0;color:var(--cb-muted);font-size:.86rem}
    .chatbuddy-pill{display:inline-flex;align-items:center;padding:8px 16px;border-radius:999px;color:var(--cb-blue);background:var(--cb-soft);font-size:.72rem;font-weight:800;letter-spacing:.01em}
    .chatbuddy-button{background:var(--cb-blue);box-shadow:none}
    .chatbuddy-button:hover{background:#21689f}
    .chatbuddy-workspace{display:grid;grid-template-columns:334px minmax(0,1fr);gap:28px}
    .chatbuddy-topic-list{display:flex;flex-direction:column;gap:8px;min-height:390px;padding-right:20px;border-right:2px solid var(--cb-line)}
    .chatbuddy-topic-list h2{margin:0;font-family:"Segoe UI",Inter,system-ui,sans-serif;font-size:1.14rem}
    .chatbuddy-topic-list .muted{margin:0;color:var(--cb-muted);font-size:.78rem}
    .chatbuddy-search{margin:10px 0 3px!important;border-color:var(--cb-line)!important;border-radius:10px!important}
    .chatbuddy-topic-list>.button{align-self:flex-start;padding:.55rem 1rem;min-height:34px;font-size:.75rem}
    .chatbuddy-topic{display:block;padding:12px 14px;border-radius:11px;text-decoration:none}
    .chatbuddy-topic{border:0;width:100%;color:inherit;background:transparent;text-align:left;cursor:pointer}
    .chatbuddy-topic:first-of-type{background:var(--cb-soft)}
    .chatbuddy-topic.is-selected{background:var(--cb-soft)}
    .chatbuddy-topic:hover{background:#f4f8fc}
    .chatbuddy-topic strong{display:block;color:var(--cb-ink);font-size:.84rem}
    .chatbuddy-topic small{color:var(--cb-muted);font-size:.7rem}
    .chatbuddy-editor{min-width:0}
    .chatbuddy-editor-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:22px}
    .chatbuddy-editor h2,.chatbuddy-bottom h2{margin:0 0 4px;font-family:"Segoe UI",Inter,system-ui,sans-serif;font-size:1.12rem}
    .chatbuddy-editor .field-row{margin-bottom:16px}
    .chatbuddy-editor textarea{min-height:4.4rem}
    .chatbuddy-editor input,.chatbuddy-editor textarea{border-color:var(--cb-line);border-radius:10px;padding:.66rem .78rem;color:var(--cb-ink)}
    .chatbuddy-editor select{width:100%;border-color:var(--cb-line);border-radius:10px;padding:.45rem;color:var(--cb-ink);background:#fff}
    .chatbuddy-link-chips{display:flex;flex-wrap:wrap;gap:5px;margin:0 0 7px}
    .chatbuddy-link-chip{display:inline-flex;padding:5px 8px;border-radius:999px;color:var(--cb-blue);background:var(--cb-soft);font-size:.68rem;font-weight:700}
    .chatbuddy-field-help{display:block;margin-top:5px;color:var(--cb-muted);font-size:.67rem;line-height:1.35}
    .chatbuddy-editor label,.chatbuddy-bottom label{font-size:.74rem;color:var(--cb-ink)}
    .chatbuddy-phrases{display:grid;gap:6px}
    .chatbuddy-phrase{min-height:auto!important}
    .chatbuddy-add-link{display:inline-block;margin-top:8px;color:var(--cb-blue);font-size:.76rem;font-weight:800;text-decoration:none}
    .chatbuddy-bottom{display:grid;grid-template-columns:1.2fr 1fr;gap:14px;margin-top:14px}
    .chatbuddy-bottom .panel{min-height:180px}
    .chatbuddy-preview-form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end}
    .chatbuddy-preview-form textarea{min-height:2.75rem;border-color:var(--cb-line);border-radius:10px}
    .chatbuddy-note{font-size:.77rem;color:var(--cb-muted)}
    .chatbuddy-settings{margin-top:14px;padding-top:12px;border-top:1px solid var(--cb-line)}
    .chatbuddy-settings summary{cursor:pointer;color:var(--cb-blue);font-size:.78rem;font-weight:800}
    .chatbuddy-settings form{margin-top:12px}
    .chatbuddy-section-nav{display:flex;flex-wrap:wrap;gap:7px;margin:14px 0}
    .chatbuddy-section-nav a{padding:8px 13px;border:1px solid var(--cb-line);border-radius:999px;color:var(--cb-blue);background:#fff;font-size:.74rem;font-weight:800;text-decoration:none}
    .chatbuddy-section-nav a:hover,.chatbuddy-section-nav a.is-active{color:#fff;background:var(--cb-blue);border-color:var(--cb-blue)}
    .chatbuddy-check{display:flex!important;align-items:center;gap:7px;margin:12px 0!important;color:var(--cb-ink)!important;font-weight:700}
    .chatbuddy-check input{width:auto}
    .chatbuddy-overview{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:16px}
    .chatbuddy-overview-card{padding:12px 14px;border:1px solid var(--cb-line);border-radius:12px;background:#fbfdff}
    .chatbuddy-overview-card strong{display:block;color:var(--cb-ink);font-size:1.05rem}
    .chatbuddy-overview-card span{color:var(--cb-muted);font-size:.7rem}
    .chatbuddy-overview-list{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}
    .chatbuddy-overview-rule{padding:7px 10px;border-radius:999px;color:var(--cb-ink);background:#f2f6fb;font-size:.72rem}
    @media(max-width:700px){.chatbuddy-overview{grid-template-columns:1fr}}
    .chatbuddy-actions{justify-content:flex-end}
    .chatbuddy-manager .button-secondary{border-color:var(--cb-line)}
    .chatbuddy-danger{margin-top:auto}
    #create-rule{margin-top:14px}
    @media(max-width:900px){.chatbuddy-workspace,.chatbuddy-bottom{grid-template-columns:1fr}.chatbuddy-topic-list{min-height:0;padding-right:0;border-right:0;border-bottom:2px solid var(--cb-line);padding-bottom:18px}.chatbuddy-topic-list .chatbuddy-topic:first-of-type{background:var(--cb-soft)}}
    @media(max-width:560px){.chatbuddy-preview-form{grid-template-columns:1fr}.chatbuddy-header .actions{width:100%;justify-content:flex-start}.chatbuddy-header{display:block}.chatbuddy-header .actions{margin-top:14px}}
</style>

@php($section = request()->query('section', request()->filled('topic_id') ? 'edit' : 'overview'))
<main class="content chatbuddy-manager">
    <section class="panel stack-sm" id="overview">
        <div class="split chatbuddy-header">
            <div>
                <h1>Chat Buddy Manager</h1>
                <p class="lede">Edit draft content, preview typed messages, then publish the reviewed release.</p>
            </div>
            <div class="actions chatbuddy-actions">
                <span class="chatbuddy-pill">DRAFT &middot; v{{ $draft->id }}</span>
                <a class="button chatbuddy-button" href="{{ route('admin.chatbuddy.releases.index', ['section' => 'preview']) }}">Review &amp; preview</a>
                <form method="POST" action="{{ route('admin.chatbuddy.releases.publish', $draft) }}" onsubmit="return confirm('Publish this reviewed draft? Students will see it immediately.');">
                    @csrf @method('PATCH')
                    <button class="button chatbuddy-button" type="submit">Publish draft</button>
                </form>
            </div>
        </div>
        @if(session('status'))<p class="badge active">{{ session('status') }}</p>@endif
        @if($errors->any())
            <div class="errors" role="alert"><strong>Some changes could not be saved.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if($section === 'overview')
        <div class="chatbuddy-overview" aria-label="Draft overview">
            <div class="chatbuddy-overview-card"><strong>{{ $draft->topics->count() }}</strong><span>Draft rules</span></div>
            <div class="chatbuddy-overview-card"><strong>{{ $draft->topics->sum(fn($item) => $item->phrases->count()) }}</strong><span>Example phrases</span></div>
            <div class="chatbuddy-overview-card"><strong>{{ $published ? 'v'.$published->id : 'None' }}</strong><span>Published release</span></div>
        </div>
        <div class="chatbuddy-overview-list">
            @foreach($draft->topics as $overviewTopic)
                <span class="chatbuddy-overview-rule">{{ $overviewTopic->title }} · {{ $overviewTopic->is_safety ? 'Safety' : 'Normal' }}</span>
            @endforeach
        </div>
        @endif
    </section>
    <nav class="chatbuddy-section-nav" aria-label="Chat Buddy Manager sections">
        <a href="{{ route('admin.chatbuddy.releases.index', ['section' => 'overview']) }}">Overview</a>
        <a href="{{ route('admin.chatbuddy.releases.index', ['section' => 'create']) }}">Create Rule</a>
        <a href="{{ route('admin.chatbuddy.releases.index', ['section' => 'edit', 'topic_id' => $selectedTopic?->id]) }}">Edit &amp; Delete Rules</a>
        <a href="{{ route('admin.chatbuddy.releases.index', ['section' => 'preview']) }}">Preview</a>
    </nav>

    @if($section === 'edit')
    <section class="panel" id="edit-rules">
        <div class="chatbuddy-workspace">
            <aside class="chatbuddy-topic-list" aria-label="Draft topics">
                <h2>Topics</h2>
                <p class="muted">Edit draft content. Published v{{ $published?->id ?? '—' }} remains live.</p>
                <input class="chatbuddy-search" type="search" placeholder="Filter topics" aria-label="Filter topics">
                <a class="button chatbuddy-button" href="{{ route('admin.chatbuddy.releases.index', ['section' => 'create']) }}">+ New topic</a>
                @forelse($draft->topics as $topic)
                    <a class="chatbuddy-topic {{ $selectedTopic?->id === $topic->id ? 'is-selected' : '' }}" href="{{ route('admin.chatbuddy.releases.index', ['section' => 'edit', 'topic_id' => $topic->id]) }}#topic-editor">
                        <strong>{{ $topic->title }}</strong>
                        <small>{{ $topic->phrases->count() }} {{ Str::plural('phrase', $topic->phrases->count()) }} &middot; {{ $topic->priority }} priority</small>
                    </a>
                @empty
                    <p class="muted">No draft topics yet.</p>
                @endforelse
            </aside>

            <div class="chatbuddy-editor" id="topic-editor">
                @php($topic = $selectedTopic)
                @if($topic)
                    @php($selectedLinkKeys = $topic->links->where('link_type', '!=', 'external')->map(fn($link) => $link->link_type.'|'.$link->target_id)->all())
                    <div class="chatbuddy-editor-head">
                        <h2>Edit topic / {{ $topic->title }}</h2>
                        <span class="chatbuddy-pill">{{ $topic->is_safety ? 'SAFETY RULE' : 'NORMAL RULE' }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.chatbuddy.releases.topics.update', $topic) }}">
                        @csrf @method('PUT')
                        <div class="field-row">
                            <div><label for="topic-title">Topic name</label><input id="topic-title" name="title" value="{{ $topic->title }}" required></div>
                            <div><label for="topic-priority">Matching priority</label><input id="topic-priority" name="priority" type="number" min="1" value="{{ $topic->priority }}" required></div>
                        </div>
                        <label for="topic-description">Description (optional)</label>
                        <textarea id="topic-description" name="description">{{ $topic->description }}</textarea>
                        <label class="chatbuddy-check"><input type="checkbox" name="is_safety" value="1" @checked($topic->is_safety)> Safety rule (requires approval before publishing)</label>
                        <label>Example messages / phrases</label>
                        <div class="chatbuddy-phrases">
                            @foreach($topic->phrases as $phrase)
                                <input class="chatbuddy-phrase" name="phrases[]" value="{{ $phrase->phrase }}" required>
                            @endforeach
                        </div>
                        <button class="chatbuddy-add-link chatbuddy-add-phrase" type="button">+ Add phrase</button>
                        <label style="margin-top:14px" for="topic-reply">Approved fixed reply</label>
                        <textarea id="topic-reply" name="reply" required>{{ $topic->reply }}</textarea>
                        <div class="field-row" style="margin-top:12px">
                            <div><label for="topic-prompts">Follow-up prompts</label><textarea id="topic-prompts" name="follow_up_prompts_text">{{ $topic->followUpPrompts->pluck('prompt')->join("\n") }}</textarea></div>
                            <div>
                                <label for="topic-typed-links">Linked content</label>
                                @if($topic->links->where('link_type', '!=', 'external')->isNotEmpty())
                                    <div class="chatbuddy-link-chips" aria-label="Current linked content">
                                        @foreach($topic->links->where('link_type', '!=', 'external') as $link)
                                            <span class="chatbuddy-link-chip">{{ Str::headline($link->link_type) }} · {{ $link->label }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                <select id="topic-typed-links" name="typed_links[]" multiple size="3" aria-describedby="topic-typed-links-help">
                                    @foreach($linkOptions as $type => $options)
                                        @foreach($options as $option)
                                            <option value="{{ $type.'|'.$option->id.'|'.($option->title ?? $option->name) }}" @if(in_array($type.'|'.$option->id, $selectedLinkKeys, true)) selected @endif>{{ Str::headline($type) }} · {{ $option->title ?? $option->name }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                                <small id="topic-typed-links-help" class="chatbuddy-field-help">Hold Ctrl/Cmd to select available activities, guidance, or resources.</small>
                            </div>
                        </div>
                        <div class="field-row">
                            <div><label for="topic-links">External links (Label|URL)</label><textarea id="topic-links" name="links_text">{{ $topic->links->where('link_type', 'external')->map(fn($link) => $link->label . '|' . $link->url)->join("\n") }}</textarea></div>
                        </div>
                        <div class="actions"><button class="button chatbuddy-button" type="submit">Save topic</button></div>
                    </form>
                    <form class="actions" style="margin-top:10px" method="POST" action="{{ route('admin.chatbuddy.releases.topics.destroy', $topic) }}" onsubmit="return confirm('Delete this draft topic? It will not reappear after deletion.');">
                        @csrf @method('DELETE')
                        <button class="button button-danger" type="submit">Delete</button>
                    </form>
                @else
                    <h2>No draft topics yet</h2>
                    <p class="muted">Use the new topic form below to add the first reviewed rule.</p>
                @endif
            </div>
        </div>
    </section>
    @endif

    @if($section === 'preview')
    <div class="chatbuddy-bottom">
        <section class="panel" id="preview">
            <h2>Preview a student message</h2>
            <p class="muted chatbuddy-note">Uses the same deterministic matcher as the student app.</p>
            <form class="chatbuddy-preview-form" method="POST" action="{{ route('admin.chatbuddy.releases.preview') }}">
                @csrf
                <input type="hidden" name="release_id" value="{{ $draft->id }}">
                <textarea name="message" aria-label="Student message" placeholder="I'm stressed about exams" required></textarea>
                <button class="button chatbuddy-button" type="submit">Test reply</button>
            </form>
        </section>
    </div>
    @endif

    @if($section === 'overview')
    <div class="chatbuddy-bottom">
        <section class="panel">
            <h2>Release controls</h2>
            <p><span class="chatbuddy-pill">{{ $published ? 'Published: v'.$published->id : 'Not published yet' }}</span></p>
            <p class="muted chatbuddy-note">Draft settings &middot; Safety approval &middot; <a href="{{ route('admin.chatbuddy.releases.history') }}">History</a></p>
            <details class="chatbuddy-settings">
                <summary>Edit draft settings</summary>
                <form method="POST" action="{{ route('admin.chatbuddy.releases.settings') }}">
                    @csrf @method('PATCH')
                    <div class="field-row"><div><label for="welcome-message">Welcome message</label><textarea id="welcome-message" name="welcome_message">{{ $draft->welcome_message }}</textarea></div><div><label for="fallback-message">Fallback reply</label><textarea id="fallback-message" name="fallback_message" required>{{ $draft->fallback_message }}</textarea></div><div><label for="safety-message">Safety response</label><textarea id="safety-message" name="safety_message">{{ $draft->safety_message }}</textarea></div></div>
                    <label for="suggested-topics">Suggested topics (one per line)</label>
                    <textarea id="suggested-topics" name="suggested_topics[]">{{ collect($draft->suggested_topics ?? [])->join("\n") }}</textarea>
                    <button class="button chatbuddy-button" type="submit">Save settings</button>
                </form>
            </details>
            @if($draft->topics->contains('is_safety', true))
                <form method="POST" action="{{ route('admin.chatbuddy.releases.approve-safety', $draft) }}">
                    @csrf @method('PATCH')
                    <button class="button button-secondary" type="submit">Approve safety content</button>
                </form>
            @endif
        </section>
    </div>
    @endif

    @if($section === 'create')
    <section class="panel" id="create-rule">
        <h2>Create a draft rule</h2>
        <p class="muted chatbuddy-note">Create a reviewed rule with example phrases and an approved response.</p>
        <form method="POST" action="{{ route('admin.chatbuddy.releases.topics.store') }}">
            @csrf
            <div class="field-row"><div><label for="new-topic-title">Topic name</label><input id="new-topic-title" name="title" required></div><div><label for="new-topic-priority">Matching priority</label><input id="new-topic-priority" name="priority" type="number" min="1" value="100" required></div></div>
            <label for="new-topic-description">Description (optional)</label><textarea id="new-topic-description" name="description"></textarea>
            <label class="chatbuddy-check"><input type="checkbox" name="is_safety" value="1"> Safety rule (requires approval before publishing)</label>
            <div class="field-row"><div><label for="new-topic-reply">Approved fixed reply</label><textarea id="new-topic-reply" name="reply" required></textarea></div><div><label for="new-topic-phrases">Example messages / phrases (one per line)</label><textarea id="new-topic-phrases" name="phrases[]" required></textarea></div></div>
            <div class="field-row"><div><label for="new-topic-prompts">Follow-up prompts</label><textarea id="new-topic-prompts" name="follow_up_prompts_text"></textarea></div><div><label for="new-topic-typed-links">Linked content</label><select id="new-topic-typed-links" name="typed_links[]" multiple size="3">@foreach($linkOptions as $type => $options)@foreach($options as $option)<option value="{{ $type.'|'.$option->id.'|'.($option->title ?? $option->name) }}">{{ Str::headline($type) }} · {{ $option->title ?? $option->name }}</option>@endforeach @endforeach</select><small class="chatbuddy-field-help">Hold Ctrl/Cmd to select available content.</small></div></div>
            <label for="new-topic-links">External links (Label|URL)</label><textarea id="new-topic-links" name="links_text"></textarea>
            <button class="button chatbuddy-button" type="submit">Save draft topic</button>
        </form>
    </section>
    @endif

</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.chatbuddy-add-phrase').forEach(function (button) {
        button.addEventListener('click', function () {
            var list = button.closest('form').querySelector('.chatbuddy-phrases');
            var input = document.createElement('input');
            input.className = 'chatbuddy-phrase';
            input.name = 'phrases[]';
            input.required = true;
            input.placeholder = 'Example student phrase';
            list.appendChild(input);
            input.focus();
        });
    });
    var filter = document.querySelector('.chatbuddy-search');
    if (filter) filter.addEventListener('input', function () {
        var query = filter.value.trim().toLowerCase();
        document.querySelectorAll('.chatbuddy-topic').forEach(function (topic) {
            topic.hidden = query !== '' && !topic.textContent.toLowerCase().includes(query);
        });
    });
});
</script>
@endsection
