# SheZen Harmony API overview

The JSON API is rooted at `/api`; versioned application routes use `/api/v1`.
Flutter obtains the base URL from `frontend/lib/core/config/api_config.dart`.

## Public endpoints

- `GET /api/health` — connectivity check.
- `POST /api/v1/auth/register` — creates a pending USP student authentication
  account, pseudonymous identity, demographics and consent, then emails an OTP.
- `POST /api/v1/auth/login` — validates student credentials and emails a fresh
  OTP challenge; it does not issue a token.
- `POST /api/v1/auth/verify-otp` — consumes a valid registration/login challenge
  and only then returns the minimal identity payload and Sanctum token.
- `POST /api/v1/auth/resend-otp` — rotates an unverified challenge after the
  configured cooldown and invalidates its previous code.
- `POST /api/v1/auth/forgot-password` — accepts `email` and always returns the
  same success message for valid addresses, whether an active student account
  exists or not. Existing students receive a reset code by email.
- `POST /api/v1/auth/verify-reset-code` — accepts `email` and a six-digit `code`;
  confirms a valid, unexpired reset code without signing the student in.
- `POST /api/v1/auth/reset-password` — accepts `email`, `code`, `password`, and
  `password_confirmation`. The code must already be confirmed. A successful
  reset consumes it, updates the password, and revokes existing sessions.
- `GET /api/v1/questions` — retained legacy active-question listing.
- `GET /api/v1/questionnaires/active` — active dynamic student questionnaire.
- `GET /api/v1/interventions` — active intervention listing.
- `GET /api/v1/wellbeing-activities` — active student wellbeing activity listing (support content and video activities).
- `GET /api/v1/helplines` — active helpline resources for the student Resource tab, in the admin's display order.
- `GET /api/v1/positive-engagement/quizzes` — active quiz listing. Games and quizzes sit under Positive Engagement.
- `GET /api/v1/positive-engagement/quizzes/{quiz}` — a single quiz with its questions.
- `POST /api/v1/positive-engagement/quizzes/{quiz}/complete` — record a quiz attempt (authenticated).

Registration, login, OTP verification, resend, and password reset are rate limited. OTP challenges
are opaque, hashed, expiring, single-use and attempt-limited. The public
questionnaire/intervention read routes must not expose student data.

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
