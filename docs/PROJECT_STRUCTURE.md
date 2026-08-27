# Project structure and ownership

This document is the Phase 1 structural baseline for the SheZen Harmony capstone.
The project deliberately uses a simple feature-first Flutter structure and
standard Laravel conventions. New layers should be introduced only when they
remove real duplication or clarify responsibility.

## Repository

```text
SheZenHarmony/
  backend/                 Laravel API and Blade administrator application
  frontend/                Flutter student application
  docs/                    Architecture, schema, and audit documentation
  scripts/                 Local Windows development helpers
  README.md                Primary setup and run guide
  SheZenHarmony.code-workspace
```

## Flutter

```text
frontend/lib/
  core/
    config/                Environment-dependent configuration
    network/               Shared HTTP client/transport
    storage/               Secure device persistence
    theme/                 Design tokens and ThemeData
  features/
    auth/
      application/         Session state and authentication orchestration
      data/                Authentication response model
      presentation/        Login and registration/onboarding UI
    assessment/
      application/         Questionnaire state and submission orchestration
      data/                Questionnaire and result models
      presentation/        Questionnaire and result screens
    home/
      presentation/        Dashboard/navigation, progress, profile
    admin_questionnaires/  Retained, currently unrouted Flutter admin prototype
      application/
      data/
      presentation/
  shared/
    widgets/               Reusable, app-wide presentation components
  main.dart                Dependency composition and root auth/assessment gate
```

Rules:

- Feature-specific code remains inside its feature.
- `core` contains framework/infrastructure code, not feature screens.
- `shared/widgets` is for widgets used or intentionally reusable across features.
- Features may depend on `core` and `shared`; `core` should not gradually become
  a second feature directory.
- Do not create empty folders for planned features. Add a feature when its first
  real model, state object, screen, or API contract is implemented.

Known structural debt: `core/network/api_service.dart` is a single large API
facade and imports feature models. It works and is covered by tests, so Phase 1
does not split it mechanically. Split it into feature clients later only when
new mood, chat, resource, or academic APIs make the boundary valuable.

## Laravel

```text
backend/
  app/
    Console/Commands/      Interactive development/administration commands
    Enums/                 Shared domain enums
    Http/
      Controllers/
        Api/               Student and JSON admin endpoints
        Web/               Session-authenticated Blade admin endpoints
      Middleware/          Cross-cutting request authorization
      Requests/            Request validation and authorization
      Resources/           JSON response transformation
    Models/                Eloquent models and relationships
    Services/              Scoring, submission, activation, validation
  database/
    factories/             Test factories
    migrations/            Append-only schema history
    seeders/               Roles and explicitly local development data
  resources/
    views/                 Blade administrator UI
    css/, js/              Vite entry points
  routes/
    api.php                Versioned JSON API
    web.php                Administrator web routes
  tests/Feature/           API, privacy, schema, and admin integration tests
```

Rules:

- Controllers coordinate HTTP concerns; services own multi-step domain work.
- Validation belongs in Form Requests where complexity or reuse warrants it.
- Do not add repository classes around Eloquent without a demonstrated need.
- Never remove or rewrite applied migrations to tidy history; add a new migration.
- Student-facing wellbeing relationships use the pseudonymous identity boundary.
- Admin responses and views must not join authentication email/name to sensitive
  wellbeing records without an explicitly approved requirement.

## Intentionally retained foundations

Some models are not yet exposed by completed UI/API flows but match planned MVP
scope and current migrations: anonymous-session compatibility, chat sessions and
messages, crisis reports, content categories/tags, intervention recommendations,
progress entries, and MFA methods. They are retained because removing them would
destroy schema intent and future-feature foundations.

The legacy public questions endpoint and Flutter admin questionnaire prototype are
also retained until the team explicitly resolves compatibility and admin-client
strategy.
