# Starter API

## Health

`GET /api/health`

Used to verify Flutter → Laravel communication.

## Development questions

`GET /api/v1/questions`

Returns active seeded development questions and their options.

The seed content is **not** an approved psychological assessment.

## Interventions

`GET /api/v1/interventions`

Optional query:

`?stress_level=low`

This endpoint is only a starter read API. Admin CRUD and recommendation logic should
be added after the project requirements are finalized.
