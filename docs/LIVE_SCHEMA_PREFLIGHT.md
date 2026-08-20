# Live-schema preflight and implementation plan

## Result

The read-only preflight passed. The connected database is MySQL 8.4.11, database `shezen_harmony`. All 11 repository migrations are recorded exactly once (maximum batch 2), and the live column types, indexes, and foreign keys match the migration-defined schema.

No migrations or data changes were executed.

## Exact live counts

| Table | Rows |
|---|---:|
| `users` | 1 |
| `sessions` | 1 |
| `personal_access_tokens` | 0 |
| `stress_questions` | 1 |
| `question_options` | 5 |
| `stress_assessments` | 0 |
| `stress_responses` | 0 |
| `interventions` | 2 |
| `intervention_usages` | 0 |

The sole current role value is `admin` (1 user). There are no assessment or intervention-usage history rows yet, so the anonymous-session conversion and historical snapshot backfills currently have no data to transform. The migration must still be written safely for other environments that may contain history.

## Data-quality and integrity checks

- Foreign-key orphan counts are zero for all seven current relationships.
- Duplicate `(stress_question_id, value)` option groups: zero.
- Duplicate `(stress_assessment_id, stress_question_id)` response groups: zero.
- All 5 current option scores are non-null.
- Anonymous UUID rows: zero in both source tables; invalid UUID rows and cross-source UUIDs: zero.
- Ownership anomalies: none because both owner-bearing history tables are empty.
- The `information_schema.tables.table_rows` values differed from exact counts for small InnoDB tables, so exact `COUNT(*)` results above are authoritative.

## Current foreign keys and approved target behavior

| Relationship | Live delete behavior | Reviewed target |
|---|---|---|
| `question_options.stress_question_id` → `stress_questions.id` | `CASCADE` | Change to `RESTRICT` after snapshots exist |
| `stress_assessments.user_id` → `users.id` | `SET NULL` | Keep |
| `stress_responses.stress_assessment_id` → `stress_assessments.id` | `CASCADE` | Keep; assessment owns its responses |
| `stress_responses.stress_question_id` → `stress_questions.id` | `CASCADE` | Change to `RESTRICT` |
| `stress_responses.question_option_id` → `question_options.id` | `SET NULL` | Change to `RESTRICT`; use deactivation |
| `intervention_usages.stress_assessment_id` → `stress_assessments.id` | `SET NULL` | Keep |
| `intervention_usages.intervention_id` → `interventions.id` | `CASCADE` | Change to `RESTRICT`; use deactivation |

`sessions.user_id` is indexed and intentionally has no SQL foreign key. Per the approved decision, it remains unchanged.

## Proposed Laravel migrations for review

Each numbered group should be deployable and reversible independently. Cleanup is deliberately separated from additive work.

1. **Extend users safely**: add nullable unique `pseudonymous_uuid`, `account_status` with default `active`, and nullable `deleted_at`. Backfill UUIDs, verify uniqueness, then decide whether to make the UUID non-null. Do not remove `role`.
2. **Add identity/access tables**: create `user_profiles`, `roles`, `user_roles`, and `user_mfa_methods`. Seed `admin`, `student`, `moderator`, and `counsellor`; also seed any unexpected live role values discovered by deployment-time preflight. Backfill `user_roles` idempotently from `users.role`.
3. **Extend question configuration**: add nullable unique `code`, nullable `help_text`, `is_required` default true, and nullable `created_by_user_id` to `stress_questions`; add `is_active` default true to `question_options`; add unique `(stress_question_id, value)` only after duplicate validation. Preserve all current names.
4. **Add anonymous-session compatibility**: create `anonymous_sessions`; add nullable `anonymous_session_fk` bigint columns to both legacy owner tables. Populate distinct UUIDs and backfill FKs. Do not remove or retype existing `anonymous_session_id` yet.
5. **Add score bands and assessment fields**: create `stress_score_bands`; add nullable `public_uuid`, `stress_score_band_id`, `assessment_type`, `assessment_status`, and nullable `started_at` to assessments. Keep `stress_level` as the historical label snapshot.
6. **Add response snapshots**: add nullable `answer_text`, `question_text_snapshot`, and `option_text_snapshot`; backfill snapshot text for any existing rows. After verifying completeness for completed assessments, decide whether `question_text_snapshot` can become non-null.
7. **Protect historical configuration**: replace the three destructive configuration/content FK behaviors shown above. Perform orphan checks immediately before dropping/recreating each FK.
8. **Extend interventions and usage**: add nullable unique `slug`, nullable `instructions`, and nullable `created_by_user_id`; create `intervention_recommendations`; add nullable `user_id`, `usage_status`, mood fields, and `duration_seconds` to usage. Keep `stress_level` and `external_url`.
9. **Add content organization**: create `content_categories`, `intervention_content_categories`, `tags`, and `intervention_tags` with their reviewed unique constraints.
10. **Add chat and crisis support**: create `chat_sessions`, `chat_messages`, and `crisis_reports`. Authorization—not merely FKs—must validate support assignments and handlers.
11. **Add progress tracking**: create `progress_entries` only for metrics that cannot be derived economically from assessments/usages; avoid duplicating derived health data without a clear requirement.
12. **Compatibility release**: deploy model/controller/API/test changes, role dual-read, and anonymous-session dual-read/write. Validate authorization parity and backfill completeness.
13. **Later cleanup, separately approved**: swap legacy UUID columns to bigint relationships and remove `users.role` only after monitored verification. No cosmetic renames are planned.

## Proposed Eloquent model changes

### Existing models

- `User`: add casts for `deleted_at` through `SoftDeletes`; add `profile()`, `roles()`, `mfaMethods()`, `stressAssessments()`, and `interventionUsages()` relationships. During transition, implement role checks against `roles.slug` with a temporary fallback to `users.role`. Keep current constants until all callers migrate.
- `StressQuestion`: retain current fillable/casts; add new fields, `creator()`, and active scopes. Deletion should be blocked when responses exist.
- `QuestionOption`: retain `label`, `value`, `score`, and `position`; add `is_active` cast and active scope. Do not delete referenced options.
- `StressAssessment`: add `user()`, `anonymousSession()`, and `scoreBand()` belongs-to relationships plus new fields/casts. During compatibility, map the bigint relation with an explicit temporary FK name.
- `StressResponse`: add missing `question()` and `option()` relationships and snapshot fields. Retain `numeric_value` and `score`.
- `Intervention`: retain `stress_level` and `external_url`; add creator, recommendations, categories, tags, and active scopes.
- `InterventionUsage`: add `assessment()`, `intervention()`, `user()`, and temporary anonymous-session relation plus status/mood/duration casts.

### New models

Add `UserProfile`, `Role`, `UserMfaMethod`, `AnonymousSession`, `StressScoreBand`, `InterventionRecommendation`, `ContentCategory`, `Tag`, `ChatSession`, `ChatMessage`, `CrisisReport`, and `ProgressEntry`. Junction tables may use `belongsToMany()` without standalone models unless pivot behavior is later required.

## Required application changes during rollout

- Replace direct role comparisons in auth controllers, admin controllers, dashboard queries, middleware paths, console commands, factories, seeders, serializers, and feature tests with centralized role helpers/policies.
- Keep API payload compatibility if clients currently expect `user.role`; derive the transitional response value from normalized roles.
- Update assessment/usage creation services to require exactly one owner at creation, while allowing both ownership columns to be null after approved erasure.
- Ensure admin deletion actions deactivate questions, options, and interventions rather than deleting records used by history.
- Add authorization policies and encrypted casts/services for MFA secrets and recovery codes. Never expose these fields through serialization.
- Add retention/anonymisation jobs only after the client approves concrete periods and legal/privacy requirements; no retention duration is assumed here.

## Approval status

The plan incorporates the approved decisions: no `sessions.user_id` FK change, no MVP multi-select, ownerless historical records permitted after erasure, no cosmetic renames, and preservation of historical assessment/intervention data. It is ready for review, but no migration implementation or execution has been authorized.
