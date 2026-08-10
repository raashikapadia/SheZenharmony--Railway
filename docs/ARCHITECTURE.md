# SheZen starter architecture

```text
Flutter mobile app
        |
        | JSON / HTTP during local development
        v
Laravel 13 REST API
        |
        | Eloquent ORM
        v
MySQL
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
