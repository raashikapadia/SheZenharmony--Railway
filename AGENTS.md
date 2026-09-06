# SheZen Harmony Development Guardrails

This file defines permanent repository-wide rules for team members and AI coding assistants. Its purpose is to support safe parallel feature development without destabilizing working features, shared contracts, privacy boundaries, or other developers' branches.

## Project Context

SheZen Harmony currently consists of:

- `frontend/`: the Flutter/Dart student mobile application.
- `backend/`: the Laravel 13/PHP backend, REST API, and active Blade administrator application.
- MySQL persistence managed exclusively through Laravel and Eloquent.
- Laravel Sanctum authentication with email OTP/MFA.
- `docs/`: architecture, schema, API, and requirements documentation.
- `scripts/`: Windows development launch helpers.

Flutter must never access MySQL directly. Laravel owns server-side authentication, authorization, validation, scoring, persistence, and the student identity boundary.

Treat the existing implementation and the documents in `docs/` as the source of truth. Extend the current system instead of redesigning it.

## Before Every Task

Internally follow this sequence before changing files:

1. Read this `AGENTS.md` completely.
2. Check the current Git branch and working-tree status.
3. Confirm the user's requested scope.
4. Inspect the relevant existing implementation and nearby conventions.
5. Search for frontend, backend, database, test, and documentation consumers of shared behavior.
6. Make a minimal implementation plan.
7. Change only necessary files.
8. Run relevant formatting, analysis, and tests.
9. Review the final Git diff and status.
10. Report exactly what changed, what was preserved, validation results, and remaining risks.

## Branch Safety

- Always check the current branch before making changes.
- Never perform feature development directly on `main` or `master`.
- If the current branch is `main` or `master`, stop and tell the developer to create or switch to a feature branch.
- Never automatically merge or push into `main` or `master`.
- Never force-push, rewrite Git history, delete branches, or reset another developer's work.
- Do not discard, overwrite, stage, or commit unrelated working-tree changes.
- Do not commit or push unless the developer explicitly requests it.

When explicitly asked to commit:

- Review the diff first.
- Stage only intended files.
- Use a descriptive commit message.
- State exactly what was committed.

When explicitly asked to push:

- Confirm that the destination is the developer's feature or integration branch.
- Never redirect the push to `main` or `master`.
- Never force-push unless the developer explicitly authorizes it after the risks are fully explained.

## Merge Safety

When asked to merge another branch:

1. Fetch the latest remote state.
2. Confirm the current destination branch.
3. State which source branch will be merged into which destination branch.
4. Ensure the destination working tree is safe before merging.
5. Merge branches one at a time.
6. If a conflict occurs, stop before editing conflicted content.
7. List every conflicting file.
8. Explain what each side changed and why Git could not combine it safely.
9. Wait for explicit resolution instructions when multiple reasonable resolutions exist.
10. Do not silently choose `ours`, `theirs`, or another side.
11. Do not push the result unless explicitly requested.

Never delete another feature merely to make a conflict easier to resolve. Preserve unfamiliar or apparently unrelated code until its ownership and purpose have been investigated.

## Preserve the Existing Architecture

Do not reorganize the project, introduce a new architectural pattern, move large numbers of files, rename major folders, replace the Laravel or Flutter architecture, convert working features to another framework, or redesign modules unless explicitly instructed.

Prefer extending existing controllers, services, models, requests, routes, screens, widgets, API clients, state objects, and database relationships. Before creating a new layer, check whether an equivalent implementation already exists and follow patterns in nearby files.

Project-specific conventions:

- Flutter is feature-first under `frontend/lib/features/`, using `data`, `application`, and `presentation` layers where they provide real value.
- `frontend/lib/core/` contains cross-cutting infrastructure, not feature screens.
- `frontend/lib/shared/widgets/` is for genuinely reusable presentation components.
- The shared `core/network/api_service.dart` facade is known structural debt but is working and tested. Do not mechanically split it; introduce feature clients only when a real boundary warrants it.
- Laravel uses framework conventions: HTTP concerns in controllers, validation in Form Requests when warranted, domain workflows in services, persistence and relationships in Eloquent models, and authorization in middleware/policies/server-side checks.
- Do not introduce repository wrappers around Eloquent without a demonstrated need.
- The active administrator application is Laravel Blade under `/admin`.
- The Flutter `admin_questionnaires` feature is an intentionally retained, currently unrouted prototype. Do not delete it or expose it from the student application without an explicit architectural decision.
- Retain existing schema foundations for planned features unless removal is explicitly approved.

## Minimum-Change Principle

> Make the smallest safe change necessary to satisfy the requirement.

- Do not refactor unrelated code while adding a feature or fixing a bug.
- Do not perform unrelated cleanup, modernization, formatting, renaming, or architectural changes.
- If an adjacent change is technically required, explain the dependency and keep the change narrow.
- Report unrelated issues separately rather than fixing them automatically.
- If a small request produces an unexpectedly large diff, investigate before continuing.

## Protect Existing Working Features

Inspect all known consumers before changing shared behavior. Preserve compatibility for:

- Authentication, registration, Sanctum sessions, email OTP/MFA, and account deletion.
- Roles, permissions, middleware, CSRF protection, and ownership checks.
- Pseudonymous student identity, demographics, consent, and privacy boundaries.
- Questionnaire configuration, versions, questions, options, score bands, submissions, and historical assessments.
- Stress scoring, results, recommendations, and intervention relationships.
- Wellbeing activities, video playback, positive engagement, games, personal guidance, notifications, and progress foundations.
- Laravel Blade admin functionality and retained Flutter admin code.
- Existing REST endpoints and Flutter API consumers.
- Analytics and reporting foundations.

Do not remove or change existing behavior merely because a new feature wants different behavior. Prefer additive, backwards-compatible changes.

## Feature Isolation and High-Conflict Files

Keep each task focused on its assigned feature. For example, adding journaling must not also change authentication, registration, questionnaires, stress scoring, unrelated dashboard sections, or unrelated models unless a genuine dependency exists.

Before editing a commonly changed file, determine whether most of the implementation can live in a feature-specific component. Make the integration point in shared files as small as practical.

Treat these areas as high-conflict:

- Dashboard and root navigation screens.
- `frontend/lib/core/network/api_service.dart`.
- Authentication providers and root application composition.
- Laravel `routes/api.php` and `routes/web.php`.
- Shared models, services, layouts, configuration, and migrations.

Do not duplicate models, services, widgets, routes, or methods to avoid understanding an existing implementation.

## Database and Migration Safety

The database schema is shared team infrastructure.

- Never modify an old migration that may already have run unless explicitly instructed and the compatibility impact is understood.
- Normally create a new migration for schema changes.
- Never automatically drop important tables, truncate data, remove populated columns, reset databases, run destructive migrations, run `migrate:fresh`, run `db:wipe`, or delete production-style data.
- Do not rename important columns or change relationships without inspecting models, migrations, services/controllers, validation, API responses, tests, and Flutter consumers.
- Preserve historical questionnaire versions, responses, scores, assessments, consent records, and pseudonymous relationships.
- Seeders must be safe for their stated environment. Demo credentials and development data must remain local/testing-only.
- Do not execute schema or data mutations merely to diagnose a problem.

## API Compatibility

Laravel and Flutter must remain contract-compatible. Before changing an endpoint URL, HTTP method, request body, response shape, JSON property, authentication requirement, validation rule, or status code:

1. Search for every backend and frontend consumer.
2. Inspect tests and documentation describing the contract.
3. Prefer additive, backwards-compatible changes.
4. Update both sides and their focused contract tests when a coordinated change is required.

Do not casually rename or remove API fields. Laravel remains the authority for validation and authorization even when Flutter performs client-side validation.

## Authentication, Authorization, and Privacy

These are sensitive, stop-and-review areas.

- Never weaken or bypass Sanctum authentication, OTP/MFA, role checks, middleware, CSRF, resource ownership, server validation, or pseudonymous identity protections.
- Student email/password data belongs to the authentication account. Sensitive wellbeing data must continue to relate through the pseudonymous student identity boundary.
- Do not expose student names, email addresses, raw internal IDs, raw pseudonymous UUIDs, questionnaire answers, or other personally identifiable/sensitive data in admin views, API responses, logs, analytics, or Flutter storage unless an approved requirement explicitly demands it.
- Flutter should store only the minimum session data already established by the project.
- Never hard-code or commit passwords, API keys, application secrets, SMTP credentials, database credentials, tokens, private service keys, or OTP codes.
- Secrets belong in environment variables. Never commit `backend/.env`.
- Do not use Laravel's log mailer for real OTP content because it exposes message bodies in logs.

If a request would weaken authentication, authorization, anonymity, or privacy, stop and explain the risk instead of implementing it.

## Dependencies and Generated Files

- Do not install, remove, upgrade, or replace packages unless required by the requested task.
- First check whether the capability already exists or an installed dependency can provide it.
- Explain why a new dependency is necessary before adding it.
- Do not perform broad Flutter, Dart, PHP, Composer, Laravel, Gradle, npm, Android, or package upgrades during unrelated work.
- Review lockfile changes and keep only those caused by the intended dependency operation.
- Do not commit build outputs, caches, reports, IDE state, temporary files, generated junk, or local environment files unless the repository intentionally tracks that exact artifact.

## Flutter Rules

When editing `frontend/`:

- Follow the current feature-first structure and nearby naming conventions.
- Reuse existing themes, widgets, services, models, and state-management patterns where appropriate.
- Preserve navigation, API integration, responsive layout, null safety, and accessibility behavior.
- Avoid unnecessary application-wide state-management changes.
- Avoid creating duplicate API clients or models for an existing contract.
- Keep video/WebView lifecycle work scoped to the relevant screen and dispose controllers/clients correctly.
- Do not persist sensitive assessment, profile, or identity data for performance convenience.
- Do not redesign unrelated screens while implementing a feature.
- Run `dart format` only on Dart files intentionally changed by the task.
- Run `flutter analyze` and focused Flutter tests when applicable.

## Laravel Rules

When editing `backend/`:

- Follow the Laravel conventions already used in this repository.
- Keep controllers focused on HTTP coordination; place multi-step scoring, submission, activation, recommendation, or other domain workflows in services where appropriate.
- Use Form Requests for complex or reused validation.
- Enforce validation and authorization server-side.
- Use Eloquent relationships consistently and avoid unnecessary raw joins across privacy boundaries.
- Preserve the separation between Sanctum-authenticated JSON APIs and session/CSRF-authenticated Blade admin routes.
- Add focused Feature tests for API, privacy, schema, and admin behavior.
- Run targeted PHPUnit tests first, then broader `php artisan test` when proportional to the change.
- Run Laravel Pint or configured static checks only on the intended scope unless explicitly asked for a repository-wide formatting pass.

## Validation Before Completion

Run the relevant available checks in proportion to the change.

For Flutter, use as applicable:

- `dart format` on intentionally modified Dart files.
- `flutter analyze`.
- Focused `flutter test` targets, followed by the full suite when warranted.
- A debug/profile build or emulator smoke test for plugin, Android, navigation, or runtime integration changes.

For Laravel, use as applicable:

- Targeted PHPUnit/Pest or `php artisan test --filter=...` tests.
- `php artisan test` for broader shared changes.
- PHP syntax checks or configured static checks.
- Focused Blade/Vite build checks for administrator UI changes.

Do not change unrelated code merely to remove pre-existing warnings. Clearly distinguish failures introduced by the current work from pre-existing or environment-related failures.

## Git Diff Review

Before declaring completion, inspect `git diff` and `git status`. Verify that:

- Only expected files changed.
- No secrets, private credentials, tokens, OTPs, or real student data were added.
- No unrelated features or architecture were altered.
- No accidental deletions occurred.
- No generated junk or build reports were added.
- No debug-only credentials escaped their explicitly local/test context.
- Dependency and lockfile changes are necessary and expected.
- Database and API impacts match the completion report.

## Stop Conditions

Stop rather than guess when:

- The current branch is `main` or `master` and the task requires feature development.
- A merge conflict has multiple reasonable resolutions.
- A request would significantly alter the established architecture.
- A change would delete, rewrite, or migrate existing user or historical assessment data.
- Requirements contradict one another or contradict an established privacy/security boundary.
- Authentication, authorization, anonymity, or privacy would be weakened.
- Another developer's feature would need to be removed or overwritten.
- The implementation requires a major breaking API change.
- The destination of a commit, merge, or push is uncertain.

Explain the blocker, the competing requirements, and safe options. Wait for explicit direction before proceeding.

## Completion Report

End development tasks with a concise report containing:

### Changed

Files and features intentionally modified.

### Preserved

Important existing functionality deliberately left unchanged.

### Validation

Formatting, analysis, tests, builds, and smoke checks run, including results and any pre-existing failures.

### Database Changes

State explicitly whether migrations, schema, seed data, or persisted data changed.

### API Changes

State explicitly whether endpoints or request/response contracts changed.

### Risks / Follow-up

Anything the developer should inspect or complete before merging.

### Git Status

State the current branch, changed files, and whether anything was committed or pushed.

## Core Principle

> **Preserve before improving. Extend before replacing. Make the smallest safe change. Never sacrifice existing working functionality simply to complete a new task.**

