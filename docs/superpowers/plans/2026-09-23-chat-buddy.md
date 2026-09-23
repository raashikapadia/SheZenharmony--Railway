# SheZen Rule-Based Chat Buddy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a database-authored, draft/published, deterministic Chat Buddy Manager and authenticated Flutter student chat while preserving existing SheZen privacy and content patterns.

**Architecture:** Add immutable release snapshots with one editable draft, topic/settings/link records, and publication logs. Laravel owns validation, matching, safety precedence, preview, and the student API; Flutter renders only API-provided content and opens approved links.

**Tech Stack:** Laravel 13/PHP, Eloquent migrations/models/services/controllers, Blade, PHPUnit/Pest feature tests, Flutter/Dart, existing `ApiService`, `url_launcher`, Provider/test fakes.

**Spec:** `docs/superpowers/specs/2026-09-23-chat-buddy-design.md`

## Global Constraints

- Every student-visible reply, phrase, prompt, label, and link comes from the published database release; no AI generation or duplicated rules in Flutter.
- Published releases are immutable. Publishing archives the previous published release and creates a fresh editable draft copied from the newly published release.
- Seeded sample rows are ordinary draft content. A completion marker makes the sample seeder a no-op after initial setup, including after admin deletion.
- No raw student messages are persisted or logged by the new flow.
- No placeholder safety contacts or crisis phone numbers are seeded or published; safety publishing requires approved content.
- Existing Sanctum authentication, `student.active`, admin middleware, pseudonymous identity boundary, API conventions, and Blade styles remain in force.
- Do not modify old migrations; add new migrations only. Do not change unrelated questionnaire, scoring, or navigation behavior.

## Review Focus

- Publishing after draft deletion must leave the old published release visible until the transaction completes, then expose the new snapshot and create its next draft; test in Task 4.
- A sample seed rerun after an admin deletes a seeded topic must not recreate it; test in Task 5.
- A published link whose target is later unpublished/deleted must disappear from the student payload while the approved reply remains; test in Task 3.
- A safety phrase matching a lower-priority normal topic must always select the published safety reply; test in Task 3.
- No published release must return a deterministic unavailable state without invented chatbot copy; test in Task 6.

---

### Task 1: Add versioned Chat Buddy schema and models

**Files:**
- Create: `backend/database/migrations/2026_09_23_000100_create_chat_buddy_releases_table.php`
- Create: `backend/database/migrations/2026_09_23_000200_create_chat_buddy_topics_table.php`
- Create: `backend/database/migrations/2026_09_23_000300_create_chat_buddy_topic_phrases_table.php`
- Create: `backend/database/migrations/2026_09_23_000400_create_chat_buddy_topic_links_table.php`
- Create: `backend/database/migrations/2026_09_23_000500_create_chat_buddy_follow_up_prompts_table.php`
- Create: `backend/database/migrations/2026_09_23_000600_create_chat_buddy_publication_logs_table.php`
- Create: `backend/app/Models/ChatBuddyRelease.php`
- Create: `backend/app/Models/ChatBuddyTopic.php`
- Create: `backend/app/Models/ChatBuddyTopicPhrase.php`
- Create: `backend/app/Models/ChatBuddyTopicLink.php`
- Create: `backend/app/Models/ChatBuddyFollowUpPrompt.php`
- Create: `backend/app/Models/ChatBuddyPublicationLog.php`
- Test: `backend/tests/Feature/ChatBuddySchemaTest.php`

**Interfaces:**
- `ChatBuddyRelease::draft()`, `ChatBuddyRelease::published()`, `topics()`, and `publicationLogs()` provide release selection and relationships.
- `ChatBuddyTopic::phrases()`, `links()`, and `followUpPrompts()` provide ordered child content.
- Link rows expose a typed target (`wellbeing_activity`, `personal_guidance`, `resource`, or approved external support record) plus the published display snapshot.

- [ ] **Step 1: Write failing schema/relationship tests** asserting required columns, release statuses, one draft/one published scope behavior, child ordering, and cascade cleanup.
- [ ] **Step 2: Run `php artisan test --filter=ChatBuddySchemaTest`** and verify the new tables/models are absent or incomplete.
- [ ] **Step 3: Add migrations** with foreign keys, status/check-compatible string fields, JSON/text fields for global suggested topics and release snapshots, approval/publication metadata, indexes for status/priority, and safe down methods.
- [ ] **Step 3a: Enforce current-release uniqueness** with a nullable `current_key` (`draft` or `published`, null for archived releases) and a unique index, then use a locked release-row/lock-record transaction in `ensureDraft()` so concurrent admins cannot create two current drafts or two current published releases.
- [ ] **Step 4: Add guarded Eloquent models** with casts, scopes, relationships, and no student-message relationship.
- [ ] **Step 5: Run the focused test again** and confirm PASS.
- [ ] **Step 6: Run `php artisan migrate:status`** and inspect the migration names without mutating data beyond the test database.

### Task 2: Implement release lifecycle, draft copying, validation, and publication audit

**Files:**
- Create: `backend/app/Services/ChatBuddyReleaseService.php`
- Create: `backend/app/Http/Requests/Admin/ChatBuddyReleaseRequest.php`
- Create: `backend/app/Http/Requests/Admin/ChatBuddyTopicRequest.php`
- Modify: `backend/app/Models/ChatBuddyRelease.php`
- Test: `backend/tests/Feature/ChatBuddyReleaseServiceTest.php`

**Interfaces:**
- `ChatBuddyReleaseService::ensureDraft(): ChatBuddyRelease`
- `ChatBuddyReleaseService::copyToDraft(ChatBuddyRelease $published): ChatBuddyRelease`
- `ChatBuddyReleaseService::publish(ChatBuddyRelease $draft, User $actor): ChatBuddyRelease`
- `ChatBuddyReleaseService::validateForPublish(ChatBuddyRelease $draft): array`
- `ChatBuddyReleaseService::archive(ChatBuddyTopic $topic): void`

- [ ] **Step 1: Write failing service tests** for creating the first draft, copying a published release into a new draft, immutable published rows, link validation, safety approval validation, atomic publish/archive, and publication log creation.
- [ ] **Step 1a: Add failing tests** proving that changing any safety phrase, safety reply, safety link/contact, or global safety response clears the approval/hash and blocks publication until the new exact content is approved.
- [ ] **Step 2: Run `php artisan test --filter=ChatBuddyReleaseServiceTest`** and verify failure.
- [ ] **Step 3: Implement the service transactionally** so publish locks the draft/current published rows, validates topics/phrases/links, archives the old published release, promotes the draft, records the actor/action, and copies the promoted snapshot to a new draft.
- [ ] **Step 4: Implement Form Requests** for title/reply/phrase requirements, duplicate/conflicting phrase detection, priority bounds, link target existence/availability, follow-up self-reference prevention, and safety approval fields.
- [ ] **Step 4a: Compute and persist a safety-content hash** over the exact safety phrases, replies, and link/contact snapshots; invalidate approval whenever that hash changes and require approval for the hash being published.
- [ ] **Step 5: Run focused tests and add assertions** that deleting a draft topic never causes the service or seeder to recreate it.
- [ ] **Step 6: Run the full backend feature subset covering migrations and release tests.**

### Task 3: Replace the matcher with published-release deterministic matching and student API

**Files:**
- Create: `backend/app/Http/Controllers/Api/ChatBuddyController.php`
- Create: `backend/app/Http/Requests/ChatBuddyMessageRequest.php`
- Modify: `backend/app/Services/ShezenChatService.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Models/ChatBuddyTopic.php`
- Test: `backend/tests/Feature/ChatBuddyMatchingTest.php`
- Test: `backend/tests/Feature/ChatBuddyApiTest.php`

**Interfaces:**
- `ShezenChatService::publishedPayload(): array`
- `ShezenChatService::payloadForRelease(ChatBuddyRelease $release): array`
- `ShezenChatService::replyForRelease(ChatBuddyRelease $release, string $message): array`
- `ShezenChatService::replyForPublished(string $message): array`
- `ChatBuddyController::index(Request $request): JsonResponse`
- `ChatBuddyController::message(ChatBuddyMessageRequest $request): JsonResponse`

- [ ] **Step 1: Write failing tests** for case/whitespace normalization, whole phrase boundaries, priority tie-breaking, safety precedence, fallback/suggested topics, unavailable-link filtering, empty-message validation, auth/student-active middleware, and no-published-release payload.
- [ ] **Step 2: Run `php artisan test --filter='ChatBuddy(Matching|Api)Test'`** and verify failure.
- [ ] **Step 3: Implement published-only release loading** with safety topics evaluated first, then normal topics ordered by priority and ID; return fixed reply, `is_safety`, follow-ups, typed links, and fallback metadata.
- [ ] **Step 3a: Make both published matching and admin preview call `replyForRelease()`** so draft preview and the student API share one matcher and cannot drift.
- [ ] **Step 4: Filter links at presentation time** when a referenced activity/guidance/resource is missing or no longer visible, while preserving the approved reply.
- [ ] **Step 5: Add `/api/v1/chat-buddy` and `/api/v1/chat-buddy/messages`** under existing authenticated active-student middleware with established JSON/error shapes and no message persistence.
- [ ] **Step 6: Run matching/API tests plus existing `ShezenChatServiceTest`** and preserve compatibility or update only tests whose contract intentionally moves to published releases.

### Task 4: Build the Blade Chat Buddy Manager, preview, deletion, and publishing UI

**Files:**
- Modify: `backend/app/Http/Controllers/Web/AdminChatBuddyController.php`
- Create: `backend/app/Http/Controllers/Web/AdminChatBuddyReleaseController.php` (or keep release actions in the existing controller if route conventions make that smaller)
- Modify: `backend/routes/web.php`
- Modify: `backend/resources/views/admin/chatbuddy/index.blade.php`
- Modify: `backend/resources/views/admin/chatbuddy/form.blade.php`
- Create: `backend/resources/views/admin/chatbuddy/settings.blade.php`
- Create: `backend/resources/views/admin/chatbuddy/preview.blade.php`
- Create: `backend/resources/views/admin/chatbuddy/history.blade.php`
- Test: `backend/tests/Feature/ChatBuddyAdminTest.php`

**Interfaces:**
- Admin routes remain session-authenticated and named under `admin.chatbuddy.*`.
- Preview POST accepts a sample message and a draft release ID and renders the same service result without publishing.
- Publish PATCH accepts the current draft and actor from the session, then redirects with an audit/status message.

- [ ] **Step 1: Write failing feature tests** for guest/student denial, admin index/edit/create, draft-only CRUD, seeded-row deletion, preview matching, invalid publish rejection, successful publish, publication history, and published-content stability after draft deletion.
- [ ] **Step 2: Run `php artisan test --filter=ChatBuddyAdminTest`** and verify failure.
- [ ] **Step 3: Add release-aware controller actions** for settings/topics/phrases/prompts/links, archive/restore/delete, preview, publish, and history using existing Blade validation/session conventions.
- [ ] **Step 4: Update Blade views** to show draft vs published status, editable global settings, repeatable content fields, typed links, safety approval gate, preview output, publication history, and explicit delete/archive actions.
- [ ] **Step 5: Run focused admin tests and inspect rendered HTML** for existing admin layout/sidebar styles and accessible form labels.

### Task 5: Replace the legacy sample seeder with draft-only, completion-guarded sample content

**Files:**
- Create: `backend/database/migrations/2026_09_23_000700_create_chat_buddy_seed_markers_table.php`
- Create: `backend/app/Models/ChatBuddySeedMarker.php`
- Create: `backend/database/seeders/ChatBuddySampleSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Modify: `backend/database/seeders/ShezenChatSeeder.php` (remove or isolate legacy unversioned inserts without deleting existing production rows)
- Test: `backend/tests/Feature/ChatBuddySampleSeederTest.php`

**Interfaces:**
- `ChatBuddySampleSeeder::FIXTURE_KEY` identifies the one-time fixture version.
- Running the seeder after the marker exists is a no-op, including when an admin deleted or edited sample rows.
- `DatabaseSeeder` must not populate a second legacy chatbot source; reconcile its existing `ShezenChatSeeder` call before registering the new seeder, while preserving already-existing legacy rows.

- [ ] **Step 1: Write failing tests** for five requested draft topics plus one global fallback reply, several phrases per topic, placeholder/client-review labeling, no safety contact, and no recreation after deletion plus a second seeder run.
- [ ] **Step 1a: Inspect and test the current `DatabaseSeeder` path** to prove `ShezenChatSeeder` is removed or isolated and cannot create a second active content source.
- [ ] **Step 2: Run `php artisan test --filter=ChatBuddySampleSeederTest`** and verify failure.
- [ ] **Step 3: Add the marker migration/model and draft-only seeder** that creates the initial draft/settings once, then records the fixture key in the same transaction.
- [ ] **Step 4: Register the new seeder in `DatabaseSeeder`** without making it publish content or restore deleted rows.
- [ ] **Step 5: Run sample seeder tests and inspect database rows** for draft status, placeholder labels, and absent safety contacts.

### Task 6: Add Flutter API models/client methods and live text chat screen

**Files:**
- Create: `frontend/lib/features/shezen/data/chat_buddy_models.dart`
- Create: `frontend/lib/features/shezen/data/chat_buddy_api.dart` (or use a focused service class consistent with nearby features)
- Modify: `frontend/lib/core/network/api_service.dart`
- Modify: `frontend/lib/features/shezen/presentation/shezen_intro_screen.dart`
- Create: `frontend/lib/features/shezen/presentation/shezen_chat_screen.dart`
- Modify: `frontend/lib/features/shezen/presentation/shezen_chat_button.dart`
- Test: `frontend/test/shezen_chat_buddy_test.dart`
- Test: `frontend/test/api_service_chat_buddy_test.dart`

**Interfaces:**
- `ChatBuddyContent.fromJson(Map<String, dynamic>)` parses `available`, welcome, and suggested topics.
- `ChatBuddyReply.fromJson(Map<String, dynamic>)` parses fixed reply text, safety flag, fallback metadata, prompts, and links.
- `ApiService.getChatBuddyContent(String token)` and `ApiService.sendChatBuddyMessage(String token, String message)` use existing authenticated request/error handling.
- `ShezenChatScreen` accepts an injectable API service for tests.

- [ ] **Step 1: Write failing Dart tests** for model parsing, unavailable first-launch content, authenticated request paths, user/bot bubble rendering, fixed reply display, fallback/safety disclosure, link actions, suggested prompt taps, loading, and error states.
- [ ] **Step 2: Run `flutter test test/api_service_chat_buddy_test.dart test/shezen_chat_buddy_test.dart`** and verify failure.
- [ ] **Step 3: Add typed Dart models and `ApiService` methods** without embedding any topic phrases, replies, URLs, or contact details.
- [ ] **Step 4: Implement the live chat screen** with a text field/send action, scrollable message list, welcome/suggested prompts, response links, accessibility semantics, setup-state notice, and explicit “not a person/emergency service” disclosure sourced from the API where appropriate.
- [ ] **Step 5: Change the Shezen entry button/intro flow** to open the live chat while preserving branding and home layout.
- [ ] **Step 6: Run focused Flutter tests, `dart format` on changed Dart files, and `flutter analyze`.**

### Task 7: End-to-end verification and documentation handoff

**Files:**
- Modify: `docs/API_STARTER.md` or the project’s current API documentation file with the final Chat Buddy contract.
- Modify: `docs/DATABASE_TABLES.md` or the current schema documentation with release/topic/link/publication tables.
- Test: existing Laravel and Flutter suites as proportionate.

- [ ] **Step 1: Run targeted Laravel tests** for schema, release lifecycle, matching/API, admin, and seeding.
- [ ] **Step 2: Run the broader Laravel test suite** and distinguish pre-existing failures from this work.
- [ ] **Step 3: Run Flutter focused tests, `flutter analyze`, and the relevant widget suite.**
- [ ] **Step 4: Review API/database docs against the actual migrations and JSON payloads.**
- [ ] **Step 5: Inspect `git diff` and `git status`** for unrelated edits, secrets, generated files, accidental deletion, or API/privacy regressions.
- [ ] **Step 6: Report changed files, database structure, admin publish workflow, student flow, sample content, tests, client-approval decisions, and current branch/status.**
