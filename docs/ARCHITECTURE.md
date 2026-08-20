# SheZen starter architecture

```text
Student Flutter app -- Sanctum token --> Laravel REST API -- Eloquent --> MySQL
                                               ^
Admin browser -------- secure session ---------|
```

The Laravel application can also host the future web-admin interface, avoiding the
need for a second web framework during the capstone.

## Data foundation included

- `stress_questions`
- `question_options`
- `stress_assessments`
- `stress_responses`
- `interventions`
- `intervention_usages`

Laravel's existing `users` table remains available for authenticated users/admins.
The `role` field separates `student` and `admin` accounts. Mobile student sessions
use revocable Sanctum tokens, while the admin website uses Laravel's cookie-based
session guard and CSRF protection.

## Authentication boundaries

- Student mobile login: `POST /api/v1/auth/login`
- Current mobile user: `GET /api/v1/auth/me`
- Mobile logout: `POST /api/v1/auth/logout`
- Admin login: `/admin/login`
- Admin dashboard: `/admin`

Public student registration is deliberately not enabled until cohort verification
and enrolment rules are approved. Administrator accounts are created interactively:

```powershell
cd backend
php artisan shezen:create-admin
```

`anonymous_session_id` supports future anonymous workflows without pretending that
the final privacy/data-retention policy has already been decided.

## Deliberately not hard-coded

The client still needs to approve/provide:

- assessment dimensions
- final questions
- scoring
- stress thresholds
- sensitive/suicide-ideation handling
- referral workflow
- final cohort/login verification rules

Therefore this starter does not implement a production stress score.
