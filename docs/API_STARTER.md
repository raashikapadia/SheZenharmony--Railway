# Starter API

## Authentication

`POST /api/v1/auth/login` authenticates a student and returns a revocable
Sanctum bearer token. The JSON body requires `email`, `password`, and
`device_name`. Administrator accounts use the web login instead.

`GET /api/v1/auth/me` returns the authenticated student. It requires an
`Authorization: Bearer TOKEN` header.

`POST /api/v1/auth/logout` revokes the token used for the current request.

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
