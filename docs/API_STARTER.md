# SheZen Harmony API overview

The JSON API is rooted at `/api`; versioned application routes use `/api/v1`.
Flutter obtains the base URL from `frontend/lib/core/config/api_config.dart`.

## Public endpoints

- `GET /api/health` — connectivity check.
- `POST /api/v1/auth/register` — creates a USP student authentication account,
  pseudonymous identity, demographics, consent, and Sanctum token.
- `POST /api/v1/auth/login` — student-only login returning a minimal identity
  payload and Sanctum token.
- `GET /api/v1/questions` — retained legacy active-question listing.
- `GET /api/v1/questionnaires/active` — active dynamic student questionnaire.
- `GET /api/v1/interventions` — active intervention listing.
- `GET /api/v1/wellbeing-activities` — active student wellbeing activity listing.

Registration and login are rate limited. The public questionnaire/intervention
read routes must not expose student data.

## Authenticated student endpoints

Send `Authorization: Bearer TOKEN` and `Accept: application/json`.

- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- `DELETE /api/v1/auth/account`
- `GET /api/v1/assessments`
- `POST /api/v1/assessments`
- `GET /api/v1/profile`
- `PUT /api/v1/profile`

Authentication payloads expose only `role`, formatted `shezen_id`, and required
assessment completion state. Assessment submission accepts question and option
IDs; Laravel validates membership and calculates the score independently.

## Administrator JSON endpoints

Routes under `/api/v1/admin` require both Sanctum authentication and the `admin`
middleware. They manage questionnaires, versions, questions/options, ordering,
activation, and score bands.

The current mobile student login deliberately rejects administrators. These JSON
routes support the retained admin-client prototype but are not reachable from the
normal Flutter student shell.

## Administrator web application

The active admin interface is under `/admin` and uses Laravel session cookies,
CSRF protection, and role middleware. It is separate from the mobile Sanctum
session boundary.

## Development warning

Development seed questionnaire content and scores are not an approved clinical or
psychological assessment. Never put authentication identity, answers, scores,
stress tiers, demographics, chat content, or SheZen IDs into third-party analytics.
