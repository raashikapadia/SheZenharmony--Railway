# SheZen Harmony codebase guide

**Start here.** This is the orientation map: what exists, where it lives, and
which handful of ideas you need before the rest of the code makes sense.

It is written to be read top to bottom once, then used as a lookup table. It
describes the code as it actually stands today, so where it disagrees with an
older document, trust this one and the code it points at.

How it relates to the other documents in `docs/`:

| Document | What it is for |
| --- | --- |
| `CODEBASE_GUIDE.md` (this file) | Orientation: what is in the code and where |
| `ARCHITECTURE.md` | The boundary and auth/privacy model, in prose |
| `PROJECT_STRUCTURE.md` | Folder ownership rules |
| `DATABASE_TABLES.md`, `*.dbml`, `ERD_VALIDATION_REPORT.md` | Schema detail |
| `API_STARTER.md` | Endpoint-by-endpoint request/response notes |
| `SHARED_DATABASE.md` | Running against the shared team database |
| `REQUIREMENTS_AUDIT.md` | Requirement coverage tracking |
| `../AGENTS.md` | The rules every change must follow |

> `ARCHITECTURE.md` and `PROJECT_STRUCTURE.md` predate several features
> (chat buddy, games, diary, guidance) and still describe a Flutter
> `admin_questionnaires` feature that is no longer in the tree. Their rules and
> boundary descriptions remain valid; their file maps do not.

---

## 1. The 60-second picture

Two applications, one database, one hard rule.

```text
Student Flutter app --Sanctum bearer token--> Laravel JSON API --Eloquent--> MySQL
                                                   ^
Admin browser --------session cookie + CSRF--------+
```

**Flutter never touches MySQL.** Laravel owns authentication, authorization,
validation, scoring, persistence, and the student identity boundary. Anything
the app appears to "decide" is really a backend-derived value it was handed.

Two entry points, deliberately separate:

- **Students** use the Flutter app, authenticate with Sanctum tokens, and hit
  `/api/v1/*`. The mobile login endpoint rejects admin accounts.
- **Admins** use server-rendered Blade pages under `/admin`, authenticated by
  the Laravel session guard with CSRF, behind `admin` middleware.

Rough scale: ~72 Dart files (~25k lines) in the app; 48 Eloquent models, 18
services, 15 API + 15 admin controllers, 60 migrations in the backend; 34
Flutter tests and 50 Laravel feature tests.

---

## 2. The five ideas to understand first

Everything else is ordinary CRUD. These five are where the real design is, and
each one has a "why" that is not obvious from the file names.

### 2.1 The pseudonymous identity boundary

This is the most important rule in the codebase and the easiest to break.

- Email, password, and name belong to the **authentication account**: `users`.
- Every piece of **wellbeing data** — assessments, responses, consent, diaries,
  gratitude, guidance favourites, chat, progress — hangs off
  `student_identities` via `student_identity_id`, never off `users` directly.
- `StudentIdentity` mints two things on create: a `pseudonymous_uuid` and a
  human-quotable `shezen_code` (`SZ` + 5 characters, alphabet excluding `0/O`
  and `1/I/L` so the ID reads the same however it is written down).

The app is told the **minimum**. After authentication the mobile payload is a
token plus four fields:

```json
{ "role": "student",
  "shezen_id": "SZ7K42P",
  "has_completed_required_assessment": true,
  "has_current_consent": true }
```

No database ID, no name, no email, no raw UUID. Read
`app/Http/Controllers/Api/AuthController.php` (the payload builder near the
bottom) alongside `frontend/lib/features/auth/data/auth_session.dart`.

Both boolean flags are **derived server-side from real rows** — completed
`stress_assessments` and current-version `student_consents` — never from a
locally stored preference. That is what lets a consent-wording change re-ask
everyone, and stops a device claiming a state it never earned.

Guarded by `tests/Feature/StudentPrivacyArchitectureTest.php` and
`frontend/test/home_privacy_test.dart`. If you are changing an admin view or an
API response and those fail, you crossed the boundary.

### 2.2 The startup gates

`frontend/lib/main.dart` is short and worth reading in full. `RootScreen`
decides the whole app from session state alone, so every launch, restart, and
deep link passes the same gates in the same order:

```text
unknown                         -> spinner
signedOut                       -> LoginScreen
signedIn + no current consent   -> SurveyConsentScreen   (cannot be skipped)
signedIn + no assessment yet    -> QuestionnaireScreen(mandatory: true)
signedIn + both done            -> HomeScreen
```

There is no router table; navigation below Home is push-based from
`home_screen.dart`. `AuthProvider` (`features/auth/application/`) is the only
app-wide state object, provided once in `main.dart`.

### 2.3 The assessment pipeline

The one genuinely complex domain flow, and it is fully configuration-driven —
nothing in the engine knows about a particular instrument, scale, or section
count.

```text
answers
  -> per-question scoring config (reverse scoring, weight)
  -> section raw / min / max, percentage x weight
  -> questionnaire scoring method
  -> total, maximum, percentage
  -> position on the result scale
  -> configured result level + recommended support
```

Two overall strategies, chosen per questionnaire by `scoring_method`:

- **`points_total`** — sum of scored points, normalised onto the result scale.
  Section weights only shape the per-section breakdown. This is the historical
  behaviour and the default for questionnaires that already exist.
- **`weighted_sections`** — each section contributes `(raw / max) x weight`.

Question **type** (`scale`, `multiple_choice`, `yes_no`) says only how a
question is *presented*. How it is answered (`answer_mode`) and how the answer
becomes points (`scoring_method`) are separate per-question settings, so the
same type behaves differently from one question to the next. Every type stores
its answers as scored `QuestionOption` rows.

Key files: `app/Services/AssessmentScoringService.php` (the authority — read its
header docblock), `AssessmentSubmissionService.php` (transaction,
duplicate-answer rejection), `RecommendedInterventionService.php`,
`RecommendedGuidanceService.php`.

### 2.4 The questionnaire lifecycle

**Exactly one questionnaire is live app-wide** at any time, and it is what
`GET /api/v1/questionnaires/active` serves. Publishing is a global switch, not a
per-family one.

```text
draft --review--> publish --> archive --> trash --(recovery window)--> purge
  |                                                                      ^
  +-- new version (same family) / duplicate (new family) ----------------+
```

- `QuestionnaireReview` reads the whole instrument and reports, in plain
  non-developer words, whether it is publishable: green checks, blocking issues,
  and non-blocking things worth a look.
- `QuestionnaireActivationService` validates then flips the live switch under a
  lock.
- `QuestionnaireVersioner` deep-clones sections, questions, options, result
  levels and linked support. `draftFrom()` continues the version family;
  `duplicate()` starts a new one.
- `QuestionnairePurger` **refuses** to purge anything with completed
  assessments — that history is permanent, and the questionnaire stays archived
  instead.

Historical versions, responses, and scores are never rewritten. This is a
capstone requirement, not a preference.

### 2.5 Chat Buddy is a rule engine, not a model

`app/Services/ShezenChatService.php`. Deterministic and fully offline:

```text
message -> crisis check -> keyword match -> intent -> response -> quick replies
```

**No LLM, no API call, no generated language anywhere.** Every word Shezen says
was typed by an administrator in the Blade admin. Crisis intents are evaluated
first and short-circuit normal matching, so a concerning message can never fall
through to a cheerful reply.

Content ships through a **draft/published release pair** (`ChatBuddyRelease`,
keyed by `current_key`). Admins edit the draft, then publish. Two safety
mechanisms worth knowing:

- Editing `safety_message` automatically clears `safety_content_approved` and
  its hash (a model `saving` hook), forcing re-approval.
- `ChatBuddyReleaseService::safetyHash()` fingerprints the safety message plus
  every safety topic's reply, phrases and links, so approval cannot silently
  carry over to changed content.

Draft content is invisible to students: `ShezenChatService::publishedPayload()`
and `replyForPublished()` only ever read the published release. See
`tests/Feature/ChatBuddyDraftIsolationTest.php`.

---

## 3. Where things live

### Repository

```text
SheZenHarmony/
  AGENTS.md         Development guardrails - read before changing anything
  README.md         Setup and run instructions
  backend/          Laravel API + Blade admin
  frontend/         Flutter student app
  docs/             This guide, architecture, schema, audits
  scripts/          Windows dev helpers (see section 7)
```

### Flutter (`frontend/lib/`)

```text
main.dart                      Composition root + RootScreen gates (2.2)

core/                          Cross-cutting infrastructure only - never screens
  config/api_config.dart       Base URL resolution (section 7)
  network/api_service.dart     The single API facade (see note below)
  platform/app_exit.dart       Closing the app from the consent gate
  storage/secure_token_storage.dart   Sanctum token at rest
  theme/app_theme.dart         AppColors / AppSpacing / AppRadii / ThemeData

shared/
  data/reference_data.dart     Dropdown reference lists (faculties, years...)
  widgets/app_ui.dart          App-wide presentation kit
  widgets/form_fields.dart     Shared form inputs + validation display

features/<name>/
  data/                        Models and per-feature persistence
  application/                 State objects / orchestration (where warranted)
  presentation/                Screens and widgets
```

Features, and what each one actually is:

| Feature folder | What it covers |
| --- | --- |
| `auth/` | Login, registration, OTP verification, password reset, survey consent |
| `assessment/` | The questionnaire, pagination, results, recommended support |
| `home/` | Dashboard, bottom navigation, stress history, profile tab |
| `activities/` | Wellbeing activities, positive engagement, all games and quizzes, helplines |
| `diary/` | Journaling: composer, library, PIN lock, offline sync |
| `guidance/` | Personal guidance cards, detail sheet, favourites |
| `profile/` | Viewing and editing the student profile |
| `shezen/` | Chat Buddy - intro and chat screens |

Only `auth` and `assessment` carry a full `application/` layer (`diary` keeps
its sync and storage under `data/`). The rest are screen-plus-model and
deliberately so — add a layer when it removes real duplication, not by default.

**Known structural debt:** `core/network/api_service.dart` is one ~966-line
facade holding every endpoint and importing feature models. It works and is
covered by tests. `AGENTS.md` says do **not** mechanically split it; introduce a
feature client only when a real new boundary warrants it.

The largest files, so you know what you are opening:
`games_quizzes_screen.dart` (2035 lines), `questionnaire_screen.dart` (1306),
`home_screen.dart` (1034), `app_ui.dart` (1016), `gratitude_jar_screen.dart`
(1001).

### Laravel (`backend/`)

```text
app/
  Enums/            AppScreen (recommendation destinations), QuestionType
  Http/
    Controllers/Api/        Student JSON endpoints
    Controllers/Api/Admin/  JSON admin (questionnaires, questions, score bands)
    Controllers/Web/        Blade admin (15 controllers)
    Middleware/             EnsureUserIsAdmin, EnsureStudentAccountIsActive
    Requests/, Resources/   Validation, JSON transformation
  Models/           48 Eloquent models
  Services/         18 services - the domain logic lives here
database/
  migrations/       Append-only schema history (60 files)
  seeders/          Roles + explicitly local/dev data
  factories/
resources/views/
  admin/            Blade admin UI, one folder per section
  layouts/admin.blade.php   Shell, nav, inline CSS, "coming soon" modal
routes/
  api.php           Versioned JSON API - high-conflict file
  web.php           Admin routes - high-conflict file
tests/Feature/      50 tests: API, privacy, schema, admin, scoring
```

---

## 4. Feature index

Use this to jump straight to whichever slice you are touching.

| Feature | Student API | Backend | Admin |
| --- | --- | --- | --- |
| Auth + OTP/MFA | `POST /v1/auth/{register,login,verify-otp,resend-otp}`, `GET /v1/auth/me`, `POST /v1/auth/logout`, `DELETE /v1/auth/account` | `AuthController`, `EmailOtpService`, `EmailOtpChallenge` | — |
| Password reset | `POST /v1/auth/{forgot-password,verify-reset-code,reset-password}` | `StudentPasswordResetController`, `StudentPasswordResetService` | — |
| Consent | `POST /v1/auth/consent` | `StudentConsent`, `StudentIdentity::recordCurrentConsent()` | — |
| Questionnaire | `GET /v1/questionnaires/active` | `QuestionnaireController`, `QuestionnairePresenter` | `/admin/questionnaires` (+ builder, review, versions, trash, scoring, preview) |
| Assessments | `GET`/`POST` `/v1/assessments`, `GET /v1/assessments/{id}` | `AssessmentController`, `AssessmentSubmissionService`, `AssessmentScoringService` | `/admin/student-stress` (+ overview, analytics) |
| Interventions | `GET /v1/interventions` | `InterventionController`, `RecommendedInterventionService` | `/admin/interventions` |
| Wellbeing activities | `GET /v1/wellbeing-activities` | `WellbeingActivityController` | `/admin/wellbeing_activities` |
| Games | `POST /v1/positive-engagement/games/{intervention}/play` | `InterventionController::play` | `/admin/positive-engagement/games` (+ visibility) |
| Quizzes | `GET /v1/positive-engagement/quizzes[/{quiz}]`, `POST .../answer`, `.../complete` | `QuizController`, `Quiz*` models | `/admin/positive-engagement/games-quizzes` |
| Gratitude | `GET`/`POST` `/v1/positive-engagement/gratitude`, `DELETE .../{id}` | `GratitudeEntryController` | — |
| Diary | `GET /v1/diary`, `POST /v1/diary/sync` | `DiaryController`, `DiarySyncService` | **none, deliberately** |
| Personal guidance | `GET /v1/personal-guidance/{for-you,current,another,favourites}`, `POST`/`DELETE` `/v1/personal-guidance/{id}/favourite` | `PersonalGuidanceController`, `RecommendedGuidanceService` | `/admin/personal-guidance` |
| Helplines | `GET /v1/helplines` | `HelplineResourceController` | `/admin/resources` |
| Chat Buddy | `GET /v1/chat-buddy`, `POST /v1/chat-buddy/messages` | `ChatBuddyController`, `ShezenChatService`, `ChatBuddyReleaseService` | `/admin/chatbuddy-manager` (+ preview, history, publish) |
| Students | — | `StudentIdentity`, `UserProfile` | `/admin/students` (+ hold / reactivate) |
| Profile | `GET`/`PUT` `/v1/profile` | `ProfileController` | — |
| Health | `GET /api/health` | `HealthController` | — |

Three things to notice in that table:

- **Public vs authenticated.** `/questions`, `/questionnaires/active`,
  `/interventions`, `/wellbeing-activities`, `/helplines` and quiz *reading* sit
  outside `auth:sanctum`. Everything student-specific sits inside
  `['auth:sanctum', 'student.active']`. Check which group you are adding to.
- **The diary has no admin surface at all.** The writing is encrypted at rest
  and reachable only by the student who wrote it. Do not add an admin route for
  it.
- **Rate limits are per-route and deliberate**: 5/min register and login, 10/min
  verify, 3 per 10 min resend and forgot-password. Keep them.

### Games: rows in the database, screens in the app

Games are the one feature split across both sides by design.

Each game has an `Intervention` row with `content_type = 'game'` and a stable
**slug**. Admins can publish, archive, and rename them — but **cannot create new
ones**, because a new row would have no screen to open (`AdminGamesController`
omits create/store, and deleting a shipped game is blocked).

The app dispatches on the slug: `breathing-challenge`, `gratitude-jar`,
`memory-spark`, and so on, mapped in
`features/activities/presentation/games/games_quizzes_screen.dart` (~line 242).
Screens live beside it in that `games/` folder.

**Adding a game means both sides**: a migration adding the row with its slug,
and a Flutter screen wired into that dispatch map. Renaming in the admin is
safe; changing a slug is not.

### Recommendation destinations

`App\Enums\AppScreen` lists the in-app screens a recommended intervention can
send a student to (`screen.wellbeing_activities`, `screen.journaling`,
`screen.chat_buddy`, ...). Values carry a `screen.` prefix precisely because the
destination and content-type vocabularies overlap and are posted on the same
form.

Both sides must know a key for it to work: the Flutter half is
`features/assessment/presentation/widgets/intervention_destination.dart`. Add a
case in one place only and the tap does nothing.

---

## 5. Suggested reading order

If you are new, or returning after time away, read in this order:

1. `AGENTS.md` — the rules.
2. `frontend/lib/main.dart` — the whole app's shape in 60 lines.
3. `app/Models/StudentIdentity.php` — the privacy boundary, with comments.
4. `backend/routes/api.php` — the complete student contract on one screen.
5. `app/Services/AssessmentScoringService.php` — read the header docblock, then
   skim the body.
6. The one feature folder you are actually here to change.

**The code comments are the documentation.** This codebase is unusually well
commented at the top of files and services, and those comments explain *why*,
not *what*. Before going looking for an external answer, read the docblock.

---

## 6. Conventions and landmines

### Treat as high-conflict

These files are edited by nearly every branch. Keep your integration point as
small as you can and put the substance in a feature-specific file.

- `backend/routes/api.php`, `backend/routes/web.php`
- `frontend/lib/core/network/api_service.dart`
- `frontend/lib/features/home/presentation/home_screen.dart` (navigation root)
- `frontend/lib/features/auth/application/auth_provider.dart`, `main.dart`
- `frontend/lib/shared/widgets/app_ui.dart`, `core/theme/app_theme.dart`
- `resources/views/layouts/admin.blade.php` (shell, nav, inline CSS)

### Database

- Migrations are **append-only**. Never edit one that may have run; add a new
  one.
- Never run `migrate:fresh` or `db:wipe` against a shared or populated database.
  See `docs/SHARED_DATABASE.md` before pointing at the team DB.
- Historical questionnaire versions, responses, scores, consent records, and
  pseudonymous relationships are permanent.
- Some models have no finished UI yet and are **intentionally retained**
  foundations, not dead code: `AnonymousSession`, `CrisisReport`,
  `ProgressEntry`, `ContentCategory`, `Tag`, `UserMfaMethod`,
  `ChatSession`/`ChatMessage`. Do not tidy them away. (`anonymous_sessions`
  existing is *not* authority to add a guest login — there is deliberately no
  guest flow.)

> **Live inconsistency worth knowing:** four migrations share two timestamps —
> `2026_09_24_000100` is used by both `establish_game_catalog` and
> `remove_library_questionnaire_purpose`, and `2026_09_24_000200` by both
> `add_app_screen_to_interventions` and `add_educational_games`. Laravel falls
> back to filename order, so they do run deterministically, but the intended
> ordering is no longer expressed by the timestamps. Worth a fresh-database run
> before relying on it.

### API compatibility

Laravel and Flutter must stay contract-compatible, and both sides are in this
repo, so there is no excuse for a one-sided change. Before touching a URL,
method, field name, status code, or validation rule: grep both sides, check
`tests/Feature/` and `frontend/test/`, prefer additive changes, and update both
halves plus their contract tests together.

### Security and privacy — stop and review

Never weaken Sanctum auth, OTP/MFA, role checks, CSRF, ownership checks,
server-side validation, or the identity boundary. Never expose student names,
emails, raw IDs, raw UUIDs, or questionnaire answers in admin views, API
responses, logs, or analytics. No secrets in the repo; `backend/.env` is never
committed. Do not use Laravel's log mailer for real OTP content — it writes
message bodies to the log.

---

## 7. Running it

Full setup is in `README.md`. The short version, from `scripts/`:

| Script | Purpose |
| --- | --- |
| `check-environment.ps1` | Verify the local toolchain |
| `configure-database.ps1` | Point the backend at a local or shared MySQL |
| `run-backend.ps1` | Serve the Laravel API and admin |
| `run-flutter.ps1` / `run-mobile.ps1` | Launch the app |
| `position-emulator.ps1` | Place the Android emulator window |

The app finds the API through `core/config/api_config.dart`, in this order:

1. `--dart-define=API_BASE_URL=http://YOUR_PC_IP:8000/api` — **required for a
   physical device**, which cannot reach the host machine's localhost.
2. `10.0.2.2:8000` on Android — the emulator's alias for the host.
3. `127.0.0.1:8000` — correct for web, Windows desktop, and the iOS simulator.

### Checks before you push

```powershell
# Flutter - from frontend/
dart format lib/path/to/only_files_you_changed.dart
flutter analyze
flutter test

# Laravel - from backend/
php artisan test --filter=TheRelevantTest
php artisan test
```

Run the targeted test first, then widen in proportion to the change. Do not
format or "fix" files your task did not touch.

### What the test suites cover

Laravel `tests/Feature/` is organised by concern, and the names are the index:
privacy (`StudentPrivacyArchitectureTest`, `OwnerValidationTest`), schema
(`SheZenSchemaCompatibilityTest`, `QuestionnaireArchitectureTest`), scoring
(`WellbeingScoringTest`, `DynamicQuestionnaireEngineTest`, `ResultScaleTest`),
the questionnaire lifecycle (builder, review, trash, versions, audit log), chat
buddy (matching, draft isolation, admin workflow), and admin access.

Flutter `test/` mirrors it: navigation and privacy on the home screen, provider
behaviour, per-screen widget tests, diary storage/lock/sync, and
`palette_contrast_test.dart`, which enforces accessible colour contrast — so a
theme change can fail it.
