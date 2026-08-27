# SheZen Harmony Flutter application

This directory contains the authenticated student application. It communicates
with the Laravel API in `../backend` and does not access MySQL directly.

## Structure

```text
lib/
  core/
    config/       Environment-aware API configuration
    network/      Shared HTTP transport and API contracts
    storage/      Secure local token persistence
    theme/        Application-wide design tokens and ThemeData
  features/
    auth/         Registration, login, session, consent, demographics
    assessment/   Dynamic questionnaire, submission, result, history models
    home/         Student dashboard, wellbeing, progress, profile shell
    admin_questionnaires/
                  Retained Flutter admin questionnaire prototype (not routed
                  from the current student application)
  shared/
    widgets/      Reusable presentation components
  main.dart       Application composition and authentication gate
test/             Widget, provider, API-contract, and privacy tests
```

Each implemented feature normally separates `data`, `application`, and
`presentation` concerns. Small features do not need empty layers.

## Run on the Android emulator

Start Laravel in one terminal from the repository root:

```powershell
.\scripts\run-backend.ps1
```

Start or reuse the `Pixel_8` emulator and run Flutter in another terminal:

```powershell
.\scripts\run-mobile.ps1
```

The Android emulator reaches the host API through
`http://10.0.2.2:8000/api`. For a physical device, provide the development
machine's LAN address:

```powershell
flutter run --dart-define=API_BASE_URL=http://YOUR_PC_LAN_IP:8000/api
```

API addresses are centralised in `lib/core/config/api_config.dart`.

## Verification

```powershell
flutter analyze
flutter test
flutter build apk --debug
```

The debug Android manifest permits clear-text HTTP for local development only.
Production builds must use HTTPS. Never put secrets or student data in Flutter
source, assets, analytics events, or logs.
