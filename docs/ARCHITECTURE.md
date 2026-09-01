# SheZen Harmony architecture

```text
Student Flutter app -- Sanctum bearer token --> Laravel JSON API -- Eloquent --> MySQL
                                                     ^
Admin browser -------- session cookie + CSRF --------|
```

## Application boundaries

- `frontend/` is the authenticated Flutter student application.
- `backend/` is the Laravel API and server-rendered administrator application.
- `docs/` contains architecture, schema, and requirements records.
- `scripts/` contains Windows development launch and environment helpers.

Flutter never connects directly to MySQL. Laravel owns authentication,
authorization, validation, scoring, persistence, and the student identity
boundary.

## Student authentication and privacy

Students register and sign in with an approved USP student email. Email and
password belong to the authentication account in `users`. Registration and valid
password login create a short-lived `email_otp_challenges` record and send a
six-digit code through Laravel mail. The OTP is stored only as a hash; no Sanctum
token exists until the challenge is verified. Laravel creates a separate
`student_identities` record and returns only this minimal mobile user payload after
authentication:

```json
{
  "role": "student",
  "shezen_id": "SZ-...",
  "has_completed_required_assessment": true
}
```

The mobile response excludes the database user ID, name, email, and raw
pseudonymous UUID. Flutter securely stores only the Sanctum token and role.
Wellbeing, demographic, consent, progress, intervention, and chat foundations
relate to `student_identity_id` where implemented.

There is no anonymous guest entry flow. `anonymous_sessions` and related nullable
compatibility columns are retained schema foundations and are not authority to
bypass student registration.

## Student API

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/verify-otp`
- `POST /api/v1/auth/resend-otp`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- `DELETE /api/v1/auth/account`
- `GET /api/v1/questionnaires/active`
- `GET /api/v1/assessments`
- `POST /api/v1/assessments`
- `GET /api/v1/interventions`
- `GET /api/v1/wellbeing-activities`

The one-time baseline assessment gate is derived from completed assessment rows
on the backend rather than a client-only preference.

## Administrator boundary

The active administrator interface is Laravel Blade under `/admin`. It uses the
Laravel session guard, CSRF protection, and the `admin` middleware. It manages
current questionnaire and intervention functionality and exposes only
pseudonymous/aggregate student information.

The Flutter `admin_questionnaires` feature is a retained prototype backed by
admin JSON endpoints. It is not currently reachable from the student app because
the mobile login endpoint deliberately rejects admin accounts. It must not be
deleted or exposed until the team decides whether administration remains
Blade-only or gains a separate authenticated Flutter entry point.

## Source organization

Flutter uses feature-first modules with `data`, `application`, and
`presentation` layers where needed. Cross-cutting infrastructure stays in
`core`; reusable presentation widgets stay in `shared/widgets`.

Laravel follows framework conventions: controllers and requests in `app/Http`,
domain models in `app/Models`, transactional/domain operations in `app/Services`,
routes in `routes`, migrations and seeders in `database`, and feature tests in
`tests/Feature`.

See `PROJECT_STRUCTURE.md` for the detailed repository map and ownership rules.
