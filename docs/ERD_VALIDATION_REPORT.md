# SheZen Harmony ERD validation

## Scope and evidence

This is a read-only comparison of all 11 migration files and all 7 Eloquent models currently in the repository against the supplied target DBML. No migration or database write was executed. Runtime migration status could not be verified because `php` is not available in the current shell; therefore “current” below means migration-defined, not confirmed against a live database.

## Corrections made to the target

- Existing application-facing names are retained: `question_options.label`, `value`, `score`, and `position`; `stress_questions.position`; `stress_assessments.stress_level`; `stress_responses.numeric_value` and `score`; and `interventions.stress_level` and `external_url`.
- Existing fields omitted by the supplied target are restored: `stress_questions.dimension` and `is_sensitive`, plus `stress_responses.numeric_value`.
- `users.role` remains as a transitional field. It must not be removed until role backfill, application cutover, and verification finish.
- `pseudonymous_uuid`, question `code`, assessment `public_uuid`, intervention `slug`, and snapshot fields are nullable initially so existing rows can be safely backfilled before `NOT NULL` is considered.
- `started_at` remains nullable on existing assessment/usage data until a backfill policy is approved.
- Ownership checks are explicitly “at most one owner.” They allow both owner fields to become null after erasure/anonymisation. Application validation must require exactly one owner when creating owned records.
- Mood checks explicitly allow `NULL`; the supplied inline column-check syntax was moved to named table checks.
- Historical question/option references use `RESTRICT`; existing destructive cascades must be replaced before relying on deactivation for history preservation.
- No `wellbeing_content` or second authenticated-session table is introduced. `interventions` is the content entity; Laravel `sessions` remains the authenticated-session store.

## Existing table and column classification

| Table | Existing unchanged | Existing requiring extension | Existing requiring rename/migration | New target fields |
|---|---|---|---|---|
| `users` | `id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at` | table is extended | `role` is retained during a staged migration to `roles`/`user_roles`; no immediate rename | `pseudonymous_uuid`, `account_status`, `deleted_at` |
| `password_reset_tokens` | `email`, `token`, `created_at` | none | none | none |
| `sessions` | `id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity` | add/verify FK only if operationally desired | none | none |
| `personal_access_tokens` | `id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at` | none | none | none |
| `stress_questions` | `id`, `question_text`, `dimension`, `question_type`, `position`, `is_active`, `is_sensitive`, `created_at`, `updated_at` | table is extended | none; proposed `position` → `sort_order` is rejected as unnecessary | `code`, `help_text`, `is_required`, `created_by_user_id` |
| `question_options` | `id`, `stress_question_id`, `label`, `value`, `score`, `position`, `created_at`, `updated_at` | table is extended; FK behavior and indexes change | proposed `label` → `option_text`, `value` → `code`, `score` → `score_value`, and `position` → `sort_order` are rejected for now | `is_active` |
| `stress_assessments` | `id`, `user_id`, `total_score`, `stress_level`, `completed_at`, `created_at`, `updated_at` | table is extended | existing `anonymous_session_id` changes from UUID text to a bigint FK through a staged compatibility migration | `public_uuid`, `stress_score_band_id`, `assessment_type`, `assessment_status`, `started_at` |
| `stress_responses` | `id`, `stress_assessment_id`, `stress_question_id`, `question_option_id`, `numeric_value`, `score`, `created_at`, `updated_at` | table is extended; FK behavior and uniqueness change | proposed `score` → `score_awarded` is rejected for now | `answer_text`, `question_text_snapshot`, `option_text_snapshot` |
| `interventions` | `id`, `title`, `description`, `content_type`, `stress_level`, `external_url`, `is_active`, `created_at`, `updated_at` | table is extended | proposed `external_url` → `content_url` is rejected for now; `stress_level` remains during recommendation cutover | `slug`, `instructions`, `created_by_user_id` |
| `intervention_usages` | `id`, `stress_assessment_id`, `intervention_id`, `started_at`, `completed_at`, `created_at`, `updated_at` | table is extended; intervention FK behavior changes | existing `anonymous_session_id` changes from UUID text to a bigint FK through a staged compatibility migration | `user_id`, `usage_status`, `mood_before`, `mood_after`, `duration_seconds` |

## Entirely new domain tables

`user_profiles`, `roles`, `user_roles`, `user_mfa_methods`, `anonymous_sessions`, `stress_score_bands`, `intervention_recommendations`, `content_categories`, `intervention_content_categories`, `tags`, `intervention_tags`, `chat_sessions`, `chat_messages`, `crisis_reports`, and `progress_entries` are new.

## Existing framework tables outside the domain ERD

The migrations also define `cache`, `cache_locks`, `jobs`, `job_batches`, and `failed_jobs`. They remain unchanged and must not be recreated by domain migrations. Their migration-defined columns are:

- `cache`: `key`, `value`, `expiration`.
- `cache_locks`: `key`, `owner`, `expiration`.
- `jobs`: `id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at`.
- `job_batches`: `id`, `name`, `total_jobs`, `pending_jobs`, `failed_jobs`, `failed_job_ids`, `options`, `cancelled_at`, `created_at`, `finished_at`.
- `failed_jobs`: `id`, `uuid`, `connection`, `queue`, `payload`, `exception`, `failed_at`.

## Model and application impact found

- `User` fillable fields, `isAdmin()`, API and web authentication, admin middleware behavior, admin dashboard counts, the admin creation command, factories, and feature tests all read `users.role`.
- `QuestionOption`, its seeder, admin validation/forms, and tests use `label`, `value`, `score`, and `position`.
- `StressQuestion`, API ordering, admin screens, and seeders use `dimension`, `position`, and `is_sensitive`.
- `StressAssessment` uses UUID `anonymous_session_id` and `stress_level`.
- `StressResponse` uses `numeric_value` and `score`.
- `Intervention`, API filtering, admin forms, seeders, and tests use `stress_level` and `external_url`.
- `InterventionUsage` uses UUID `anonymous_session_id`.

These references make immediate renames unsafe. A compatibility release or deliberate application cutover is required first.

## Constraint and relationship audit

- All target bigint foreign keys match Laravel `$table->id()` (`BIGINT UNSIGNED`). The two existing anonymous identifiers do not match and require the staged conversion below.
- Existing `sessions.user_id` is indexed but has no SQL foreign key because its migration uses `foreignId(...)->index()` without `constrained()`. The corrected target proposes `ON DELETE CASCADE`; adding it is optional and must first check for orphan rows.
- Existing `question_options.stress_question_id` cascades on question deletion. Target behavior is `RESTRICT` to preserve historical response meaning.
- Existing `stress_responses.stress_question_id` cascades, and `question_option_id` uses `SET NULL`. Target behavior is `RESTRICT` for both, with inactive configuration retained.
- Existing `intervention_usages.intervention_id` cascades. Target behavior is `RESTRICT` to retain usage history.
- Existing `stress_assessments.user_id` and usage-to-assessment links already use `SET NULL`, which matches preservation goals.
- A unique `(stress_assessment_id, stress_question_id)` assumes one response per question per assessment. This is correct only while multi-select questions are unsupported.
- A unique `(stress_question_id, value)` safely maps the current option `value` to the target concept of a per-question option code. Duplicate data must be checked before adding it.
- A unique nullable `crisis_reports.chat_message_id` is valid in MySQL and permits multiple reports with no source message, while allowing at most one report per non-null message.
- Role membership cannot be enforced by FK alone for support assignees/handlers; application authorization must ensure the referenced user has an allowed role.
- MySQL 8.0.16+ enforces `CHECK` constraints. On earlier versions these need application validation or triggers.

## Safe anonymous-session migration

1. Create `anonymous_sessions` with bigint `id` and unique `public_uuid`.
2. Add temporary nullable `anonymous_session_fk` bigint columns to `stress_assessments` and `intervention_usages`; do not alter the existing UUID columns.
3. Insert one `anonymous_sessions` row for every distinct non-null legacy UUID across both tables. Use the UUID as `public_uuid`; derive conservative timestamps from the earliest related `created_at`/`started_at`.
4. Backfill both temporary FK columns by joining legacy UUID to `anonymous_sessions.public_uuid`.
5. Verify: no non-null legacy UUID lacks a new FK; distinct UUID counts match; row counts and assessment/usage ownership are unchanged.
6. Deploy application code that writes/reads the bigint relation while optionally dual-writing the legacy UUID during rollout.
7. After verification, replace the legacy `anonymous_session_id` column with the bigint relation using a separate rename/swap migration. Keep a backup mapping table or export until rollback risk has passed.
8. Add FKs with `ON DELETE SET NULL`, then add at-most-one-owner checks after cleaning any rows that have both registered and anonymous owners.

## Safe role migration

1. Create `roles` and `user_roles` while retaining `users.role` and its index.
2. Seed normalized role slugs for every distinct non-null legacy value, including at least `student` and `admin`; explicitly review unknown values rather than silently mapping them.
3. Backfill one `user_roles` row per user from `users.role`, idempotently under the unique `(user_id, role_id)` constraint.
4. Add Eloquent relationships and authorization helpers that prefer `user_roles` but temporarily fall back to `users.role`.
5. Update authentication checks, middleware, dashboard queries, the admin command, factories, seeders, API serialization, and tests.
6. Deploy and verify parity: every user has the expected role assignment, admin access tests pass, and legacy/new authorization decisions match.
7. Stop writing `users.role`, monitor the transition, and only then remove the legacy column and constants in a separately approved migration.

## Proposed migration sequence (not executed)

1. Preflight the live database: migration status, MySQL version, engine/collation, row counts, nulls, orphan FKs, duplicate option values, duplicate UUIDs, and unexpected role values.
2. Add nullable/non-breaking fields to existing tables. Preserve every legacy field and default during this phase.
3. Create `roles`, `user_roles`, `user_profiles`, and `user_mfa_methods`; seed/backfill roles.
4. Create `anonymous_sessions`; add temporary bigint ownership columns and backfill both legacy UUID sources.
5. Create `stress_score_bands`; backfill bands and link assessments without changing `stress_level` snapshots.
6. Add response snapshot fields and populate them from current questions/options before changing delete behavior.
7. Replace destructive question, option, and intervention foreign-key delete rules with history-preserving rules after orphan checks.
8. Create intervention recommendation, category, tag, and junction tables; optionally backfill recommendations from `interventions.stress_level`.
9. Create chat, crisis, and progress tables.
10. Deploy model/controller/test compatibility changes and begin dual-read/dual-write transitions for roles and anonymous ownership.
11. Validate counts, mappings, authorization parity, historical results, FK integrity, and rollback artifacts.
12. In later separately approved cleanup migrations, swap anonymous UUID columns to bigint FKs and optionally remove `users.role`. Column renames should occur only if their benefit justifies an application-wide compatibility release; this validated design does not require them.

## Approval gates

No migration should be written until the live-schema preflight is available and the following choices are approved: whether `sessions.user_id` gets an FK, whether multi-select questions are planned, whether old anonymous records may remain ownerless, the retention/anonymisation policy for mental-health data, and whether any cosmetic column renames are still desired.
