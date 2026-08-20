# SheZen Harmony requirements audit

Audit date: 2026-08-13

## Scope and limitation

No numbered FR/NFR specification is present in this repository. This matrix therefore covers only the requirements explicitly named in the review request. It must not be treated as a complete traceability matrix until the approved specification (including every FR and NFR, scoring rules, crisis protocol, privacy policy, and acceptance criteria) is supplied.

Status meanings: **Implemented** works end-to-end; **Partially Implemented** has working foundations but not the complete user flow; **Missing** has no working implementation; **Needs Clarification** cannot be implemented safely without an approved rule or policy.

## Functional requirements checklist

| Requirement supplied in review request | Status | Repository evidence / conflict |
|---|---|---|
| Student registration | Needs Clarification | No registration route or Flutter screen. `docs/ARCHITECTURE.md` deliberately blocks public registration pending cohort verification and enrolment rules. Required identity fields, eligibility, verification, consent, and duplicate-account handling are unspecified. |
| Student login | Partially Implemented | Sanctum login, profile, logout, throttling, role restriction, and backend tests exist in `AuthController`, `routes/api.php`, and `MobileAuthTest.php`; Flutter has API methods but no login UI or secure token persistence. |
| Anonymous access | Partially Implemented | Questions and interventions are public. There is no anonymous-session creation, lifecycle, client persistence, ownership proof, or anonymous assessment API. |
| Anonymous user IDs | Partially Implemented | Nullable UUID columns exist on assessments and intervention usage, but nothing generates, validates, binds, rotates, or authorizes them. Both `user_id` and `anonymous_session_id` can be null or simultaneously populated. |
| Stress assessments | Partially Implemented | Question/option schema and read API exist. Assessment/response tables exist. No submission API, server-side scoring, completion transaction, Flutter questionnaire flow, or approved production questionnaire exists. |
| Stress history | Missing | No history endpoint or Flutter history UI. Ownership/anonymous access rules and retention rules are unspecified. |
| Intervention recommendations | Partially Implemented | Active interventions can be filtered by caller-supplied `stress_level`; there is no assessment-to-recommendation workflow and the API does not validate allowed levels. Approved thresholds are missing. |
| Rule-based Chat Buddy | Missing | No schema, rules engine, routes, services, or UI. Conversation scope, allowed responses, escalation triggers, and retention/logging rules need clarification. |
| Crisis-support handling | Needs Clarification | `is_sensitive` exists on questions, but there is no detection or response workflow. The repository explicitly says sensitive/suicide-ideation handling and referrals await approval. Emergency contacts, wording, triggers, jurisdiction, and human escalation must be supplied. |
| Wellbeing activities | Partially Implemented | Intervention records and a public listing API exist with demo breathing/journaling content. Flutter only shows placeholders; completion tracking has schema but no API/UI. |
| Notifications | Missing | Laravel's framework notification trait/queue tables are not a feature implementation. There are no preferences, schedules, device tokens, notification classes, jobs, or Flutter integration. Timing, channels, consent, and quiet-hour rules need clarification. |
| Admin dashboard | Partially Implemented | Secure role-gated login and aggregate counts work. The dashboard now links to questionnaire and intervention management. Full reporting requirements are unavailable. |
| Admin content management | Partially Implemented | Admin CRUD now manages intervention records dynamically. Other configurable content types named by a complete specification are unknown. Historical intervention records are deactivated rather than deleted when used. |
| Admin questionnaire management | Implemented | Authenticated admins can create, list, edit, activate/deactivate, and delete questions/options. Questions with response history are deactivated instead of deleted. Only the currently supported `scale` type is allowed; additional required types need specification. |
| Analytics | Partially Implemented | Dashboard exposes only aggregate counts, not student-level sensitive answers. Required measures, date filters, anonymisation thresholds, exports, and retention are unspecified. |
| Privacy | Partially Implemented | Passwords and remember tokens are hidden; dashboard uses aggregate counts. However there is no consent record, retention/deletion workflow, privacy notice, purpose limitation, anonymous ownership model, audit log, or analytics suppression policy. Laravel database sessions store IP address and user agent by default; whether this is permitted needs clarification. |
| Security | Partially Implemented | Password hashing, Sanctum tokens, admin RBAC, CSRF-protected web forms, login throttling, validation, hidden credentials, and environment-secret guidance exist. API questions/interventions are intentionally public. Missing areas include production HTTPS enforcement/configuration, token expiry policy, registration rate limits, password reset/verification, security headers, audit logs, and authorization tests for future private records. |
| Account management | Missing | No registration, profile editing, password change/reset, email verification, session/device management, account deletion, or Flutter account UI. Exact privacy and deletion semantics need clarification. |

## Non-functional requirements checklist

Every formal NFR is **Needs Clarification** because no NFR specification or measurable acceptance targets were supplied. The codebase has useful foundations—Flutter cross-platform structure, Laravel validation/testing, MySQL migrations, environment configuration, and basic responsive admin markup—but compliance cannot be asserted without targets for performance, availability, scalability, accessibility, usability, compatibility, reliability, backup/recovery, observability, privacy, and security.

## Confirmed code conflicts and risks

1. `stress_assessments` permits records with neither or both identity fields; this conflicts with a reliable registered-or-anonymous ownership model.
2. `intervention_usages` has an anonymous UUID but no `user_id`; registered-user activity can only be associated indirectly through an optional assessment.
3. `InterventionController` accepts an arbitrary unvalidated stress-level filter and does not produce recommendations from an assessment result.
4. Flutter labels major features as ready for a future phase and exposes a development API connection panel; this is not a production user flow.
5. Mobile authentication returns standard user fields and the client keeps tokens only in memory; production secure storage is absent.
6. Demo questions and scores must not be used as an approved clinical/wellbeing assessment.
7. Public APIs return `is_sensitive`, option scores, and internal timestamps. Whether clients should receive scoring configuration is a privacy/integrity decision; clarify before changing the response contract.
8. Logs and compiled runtime artifacts exist locally. No application code currently logs student answers, but a formal redaction and retention policy is absent.

## Recommended implementation order

1. Approve the FR/NFR specification and resolve identity, consent, privacy/retention, scoring, crisis, referral, and notification rules.
2. Implement the registered-or-anonymous identity model with database constraints and authorization tests.
3. Implement transactional assessment submission, approved server-side scoring, history access, and crisis handling.
4. Connect the Flutter onboarding/login/anonymous flow, assessment flow, results, recommendations, history, and secure local session storage.
5. Complete intervention/activity usage and rule-based Chat Buddy using admin-configurable approved rules/content.
6. Add account management and notification consent/preferences/delivery.
7. Expand privacy-preserving admin analytics and remaining configurable content management.
8. Verify every formal NFR with measurable automated/manual acceptance tests and production configuration review.

## Files expected to need modification next

- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/AuthController.php`
- New API controllers, request validators, policies, services, and resources under `backend/app/Http/`
- Models under `backend/app/Models/`
- New migrations under `backend/database/migrations/`
- Admin routes/controllers/views under `backend/routes/web.php`, `backend/app/Http/Controllers/Web/`, and `backend/resources/views/admin/`
- Feature tests under `backend/tests/Feature/`
- `frontend/pubspec.yaml`
- `frontend/lib/main.dart`
- `frontend/lib/core/network/api_service.dart`
- `frontend/lib/features/auth/` and new feature modules for assessment, history, activities, Chat Buddy, crisis support, notifications, and account management
- Flutter tests under `frontend/test/`

The exact set depends on the approved requirements and must be updated when those documents are provided.
