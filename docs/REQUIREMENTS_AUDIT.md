# SheZen Harmony requirements audit

Audit refreshed: 2026-08-27

## Scope

This is a repository-evidence audit, not a substitute for the approved capstone
FR/NFR specification. Clinical content, crisis wording, referral rules,
accessibility targets, retention periods, and acceptance criteria still require
formal approval before production claims can be made.

Status meanings: **Implemented** has a connected code path and automated coverage;
**Partial** has a working foundation or prototype but not the full requested MVP
flow; **Missing** has no active implementation; **Decision required** must not be
completed safely without an approved product/privacy rule.

## Current functional baseline

| Capability | Status | Repository evidence / limitation |
|---|---|---|
| Student registration | Implemented | USP-domain validation, password confirmation, demographics, consent, Sanctum token creation, Flutter onboarding, and tests exist. Email verification/cohort verification is not implemented. |
| Student login/session/logout | Implemented | Student-only Sanctum login, minimal `/auth/me` response, revocable logout, secure Flutter token persistence, restoration handling, and tests exist. |
| Anonymous guest access | Intentionally unavailable | The Flutter root gate requires an authenticated student. Compatibility schema for anonymous sessions is retained but is not an active guest flow. |
| SheZen Anonymous ID | Implemented | A stable `student_identities` record is created and only the formatted `shezen_id` is returned to Flutter. Mobile payloads omit user ID, name, email, and raw UUID. |
| Privacy consent | Implemented (draft policy) | Registration requires acceptance and stores a versioned consent against `student_identity_id`. Final policy wording/version and analytics consent remain product decisions. |
| Demographics | Implemented | Approved current fields are collected during registration and stored behind the student identity boundary. Final approved field list requires confirmation. |
| Baseline stress assessment | Implemented foundation | The first-login gate uses the active dynamic questionnaire; answers are submitted and scored server-side. Seed questions are development content, not an approved final instrument. |
| Stress history/check-ins | Implemented foundation | Authenticated students can repeat the active check-in and view their completed assessment summaries. Clinical interpretation and production content remain unapproved. |
| Mood tracking | Missing | No distinct mood model, API, or active screen. Dashboard entry is a labelled future feature. |
| Wellbeing activities | Partial | Intervention schema, public listing endpoint, admin CRUD, and foundations for recommendations/usage exist. Student activity screens and completion tracking API are not complete. |
| Rule-based Chat Buddy | Partial foundation | Chat/session/message/crisis schema models exist, but no approved conversation tree, API, or Flutter flow is active. It must remain menu/path driven rather than unrestricted AI. |
| Resources/categories | Partial foundation | Content category/tag relationships exist around interventions. A dedicated student resources experience and complete admin management are not active. |
| Academic support | Missing | Dashboard placeholder only; courses, deadlines, and reminder persistence are not implemented. |
| Notifications | Missing | No preferences, device-token integration, scheduled delivery, or notification UI exists. |
| Student profile | Partial | Anonymous ID, privacy/help placeholders, progress, and logout are available. Account deletion exists in the API but is not exposed in the Flutter profile. |
| Admin authentication/RBAC | Implemented | Laravel session login, CSRF, admin middleware, command-created admins, and access tests exist. |
| Admin questionnaires | Implemented foundation | Blade CRUD and protected JSON management cover questionnaires, versions, questions/options, ordering, activation, and score bands. |
| Admin interventions | Implemented foundation | Blade management exists; broader content/resource workflows remain incomplete. |
| Admin privacy-safe analytics | Partial | Aggregate dashboard and pseudonymous student listing exist. Full approved metrics, suppression thresholds, filters, and exports are not defined. |
| Firebase/GA4 analytics | Deliberately deferred | No analytics SDK is configured. Only privacy-safe, non-sensitive usage events may be added after core flows and consent requirements are stable. |

## Confirmed architectural protections

1. Mobile authentication responses exclude email, name, database user ID, and raw
   pseudonymous UUID.
2. Flutter persists only the token and role and deletes legacy locally cached PII
   keys.
3. Student demographics, consent, assessments, intervention usage, progress, and
   chat foundations use `student_identity_id` where implemented.
4. Assessment score calculation and required-answer checks run on Laravel, not on
   trusted client-provided scores.
5. Administrator web routes use session authentication, CSRF protection, and role
   middleware; administrator JSON routes use Sanctum plus the admin middleware.
6. Applied migration history is retained and guarded by schema/privacy tests.

## Decisions still required

- Approved stress and mood instruments, dimensions, options, thresholds, and
  interpretation language.
- Crisis detection, emergency wording, referral contacts, human escalation, and
  retention policy.
- Final privacy notice, analytics consent model, deletion/retention semantics, and
  aggregate-reporting thresholds.
- Rule-based Chat Buddy paths and approved responses.
- Academic data source and notification channel/quiet-hour requirements.
- Whether the retained Flutter questionnaire-admin prototype should be connected,
  separated into another client, or retired in favour of Blade after explicit
  team approval.

## Recommended delivery order after Phase 1

1. Fix verified implementation/integration defects without changing established
   privacy contracts.
2. Add student account deletion UI and complete intervention/resource usage paths.
3. Implement approved mood tracking and rule-based Chat Buddy content.
4. Add simple academic and notification prototype flows.
5. Complete missing admin content workflows and agreed aggregate analytics.
6. Perform UI/accessibility/device testing.
7. Add privacy-safe Firebase/GA4 events only after consent and event governance are
   approved.
