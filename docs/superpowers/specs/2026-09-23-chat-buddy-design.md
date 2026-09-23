# SheZen Rule-Based Chat Buddy Design

## Goal

Deliver a database-authored, deterministic Chat Buddy for SheZen. Authorised admins manage draft content, preview matching, and publish immutable student-visible snapshots. Authenticated students type messages in Flutter and receive only approved Laravel replies and links.

## Existing foundation

The repository already contains:

- `chat_intents`, `chat_responses`, and `chat_quick_replies` tables and Eloquent models.
- `ShezenChatService`, which performs deterministic keyword matching and gives safety intents precedence.
- A Laravel Blade Chat Buddy CRUD screen behind the existing `auth` and `admin` middleware.
- A Flutter Shezen entry point and static “Coming Soon” preview.
- Existing wellbeing activities, personal guidance, and helpline/resource content that must remain the source of linked destinations.

The implementation extends these conventions and keeps existing authentication, privacy boundaries, and API patterns intact.

## Content and versioning model

Use a versioned Chat Buddy release model rather than exposing mutable admin rows directly to students.

### `chat_buddy_releases`

One row represents a complete content snapshot. It stores:

- status: `draft`, `published`, or `archived`;
- publication metadata (`published_at`, `published_by_user_id`, `created_by_user_id`);
- editable global wording: welcome message, fallback reply, safety response, and suggested topic labels;
- `safety_content_approved` and approval metadata;
- timestamps.

There is at most one current draft and one current published release. Published releases are immutable and are never edited in place. Publishing atomically archives the previous published release, promotes the draft, and creates a new editable draft copied from the newly published release. Students always query the current published release. If draft content is deleted after a publish, the published snapshot remains unchanged until another publish.

### `chat_buddy_topics`

Topics belong to exactly one release and store:

- title and optional description;
- matching priority;
- safety-rule flag;
- one approved reply;
- ordered example phrases/keywords;
- ordered follow-up prompts;
- ordered links to existing activities, personal guidance, resources, or approved support contacts;
- timestamps.

The seeded examples are ordinary draft topic rows. Admin deletion is a normal delete from the draft and does not trigger reseeding or copy-back. A published topic remains in its release snapshot after the draft topic is deleted until the draft is published.

Links use typed references to existing database records where possible (activity, guidance, or resource IDs). A publish validation checks that every linked target exists and is currently published/available; invalid links block publication. The published topic stores the link target snapshot needed for deterministic presentation. If a linked activity/resource is later deleted or unpublished, the student API keeps the approved reply but omits that unavailable link and reports no raw internal target details. External/support links require an approved content record; no crisis phone number or placeholder contact is placed in code or seed data.

### Publication history

`chat_buddy_publication_logs` records publish/archive actions, actor, source release, resulting status, and timestamp. It does not store student messages.

The pre-existing intent/response tables remain readable for compatibility with existing schema tests and migration history. The live service and new admin workflow use the versioned release tables; no existing student data is rewritten.

## Matching behavior

Laravel owns matching and validation. The service loads only the current published release:

1. Normalize the submitted message for case and surrounding whitespace.
2. Reject empty input with a validation error at the API boundary.
3. Evaluate published safety topics first, ordered by ascending priority then stable topic ID.
4. If no safety topic matches, evaluate normal topics using the same deterministic order.
5. A phrase matches as a whole word/phrase boundary, case-insensitively; multiple matching topics resolve to the first topic in the order above.
6. Return the topic reply, safety flag, typed links, and follow-up prompts.
7. If no topic matches, return the published fallback reply and published suggested topics.

The service has no AI/model calls and never generates or invents text. Preview uses the same matcher against a selected draft release without making it student-visible.

## Admin workflow

The existing Chat Buddy Manager becomes a complete release editor:

- View the current draft, current published release, archived releases, and publication history.
- Edit global wording and suggested topics.
- Create, edit, archive, restore, and delete draft topics. Seeded rows have no special behavior and do not reappear after deletion.
- Manage phrases, priority, safety flag, one reply, follow-up prompts, and typed links.
- Preview a sample message and see the matched topic, safety precedence, reply, links, or fallback result.
- Validate required reply/title/phrase content, empty phrase rows, duplicate/conflicting phrases within a release, invalid links, self-referential follow-ups, and safety approval requirements.
- Publish only a valid draft. Publishing requires approved safety information before any safety path can become student-visible. The operation is transactional and creates an audit record.

Admin permissions use the existing `auth` plus `admin` middleware and current Blade authorization conventions.

## API contract

Add authenticated student endpoints under `/api/v1` and the existing `auth:sanctum`, `student.active` middleware:

- `GET /chat-buddy` returns published welcome text and suggested topics.
- `POST /chat-buddy/messages` accepts `{ "message": "..." }` and returns the deterministic reply, `is_safety`, typed links, follow-up prompts, and fallback metadata.

Before the first release is published, both endpoints return HTTP 200 with `available: false`, an empty suggested-topic list, and no chatbot wording or links. The Flutter screen shows a non-chatbot setup-state notice and does not fabricate a welcome, fallback, or safety reply. Once a release is published, the same endpoints return `available: true` and published content. Responses contain only published, admin-authored content and safe public identifiers/URLs already used by the app. Student message text is processed in memory and not persisted. Existing API error conventions handle validation, unauthenticated access, inactive students, and unavailable published content.

## Flutter flow

The existing Shezen entry point opens a live chat screen. The screen:

- fetches the published welcome/suggested topics;
- lets the student type and send a message;
- renders user and fixed bot bubbles in order;
- shows loading and API error states using existing app conventions;
- renders approved activity/resource/support links and follow-up prompt chips;
- clearly states that Shezen is a rule-based companion, not a person, counsellor, or emergency service;
- never embeds topic names, phrases, replies, URLs, or contacts in Dart.

The shared `ApiService` facade is extended only for this real feature boundary, with injectable methods to keep screen tests deterministic.

## Seed content

Add a draft-only sample seeder/import fixture with placeholder labels for:

- exam stress;
- feeling overwhelmed;
- breathing activity;
- study breaks;
- finding support;
- unmatched-message fallback.

Each topic has several example phrases and a fixed placeholder reply, clearly marked for client review. The unmatched-message fallback is a global release setting, not a topic and has no phrases. No safety topic or crisis contact is enabled by the seed. The seeder uses a completion marker keyed to the fixture version and runs only during initial setup; once that marker exists it is a no-op, even if an admin has deleted or edited any seeded row. Deleting a seeded row through the manager therefore removes it from the draft permanently unless an admin explicitly creates it again.

## Privacy and safety

Do not create chat sessions/messages for ordinary matching. Existing chat-session/crisis-report foundations remain untouched unless a future approved requirement explicitly needs persistence. No raw student messages are logged. Safety responses are informational and must not imply human monitoring or emergency care.

## Testing and verification

Laravel tests cover:

- phrase matching, deterministic priority, and safety precedence;
- fallback and suggested topics;
- draft isolation from published content;
- deletion of seeded topics and persistence of the published snapshot until publish;
- publish validation and publication history;
- preview behavior;
- admin authorization and API authentication/active-student rules.

Flutter tests cover published content parsing, message submission/error states, fixed reply rendering, safety disclosure, and linked-content actions using a fake API service. Run focused Laravel tests, Flutter formatting/analyze/tests for changed files, then review the final diff/status.

## Decisions requiring client approval

- Exact wording for welcome, fallback, safety, topic replies, and follow-up prompts.
- Approved support contacts and external URLs before enabling/publishing any safety topic.
- Whether links should expose only in-app destinations or also approved external resources.

All supplied seed wording remains placeholder content and stays in draft until reviewed.
