# SheZen Harmony

## Technology Stack
- Flutter/Dart
- Laravel 13 / PHP 8.4
- MySQL 8.4
- Laravel Sanctum
- Git/GitHub
- Android Studio
- VS Code

## Prerequisites
Developers need:
- Git
- VS Code
- Flutter
- Android Studio + Android SDK
- Laravel Herd or PHP 8.4 + Composer
- MySQL 8.4

Verification commands:

```powershell
php -v
composer -V
flutter --version
flutter doctor
git --version
mysql --version
```

## Clone the Repository

```powershell
git clone https://github.com/gwennatly01/SheZenHarmony.git
cd SheZenHarmony
```

## Backend Setup

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Configure your own local MySQL password in `backend/.env`.

Example local values:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shezen_harmony
DB_USERNAME=root
DB_PASSWORD=YOUR_LOCAL_PASSWORD
```

Student registration and login use email OTP verification. For local development,
run a local SMTP inbox such as Mailpit and keep these values in `backend/.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@shezen.local"
USP_STUDENT_EMAIL_DOMAIN=student.usp.ac.fj
MFA_OTP_EXPIRES_MINUTES=10
MFA_OTP_MAX_ATTEMPTS=5
MFA_RESEND_COOLDOWN_SECONDS=60
```

Mailpit exposes the development inbox at `http://127.0.0.1:8025`. For deployment,
replace the SMTP host, port, username, password and sender with real provider
credentials. Do not use Laravel's `log` mailer for OTP email because it writes the
message body to application logs.

Create the database:

```powershell
mysql -u root -p
```

```sql
CREATE DATABASE shezen_harmony
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Then run:

```powershell
php artisan config:clear
php artisan migrate --seed
```

Create the first administrator account without storing its password in source:

```powershell
php artisan shezen:create-admin
```

For repeatable local demo data, run the development-only seeder explicitly:

```powershell
php artisan db:seed --class=DevelopmentSeeder
```

This creates `admin.demo@shezen.local` / `Admin1234!` and
`student.demo@student.usp.ac.fj` / `Student1234!`, plus a complete development-only
stress questionnaire. These credentials are fake, local-only test credentials;
never use them in production. The seeder refuses to run outside the `local` and
`testing` environments and is safe to run repeatedly.

## Run Laravel

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

Health endpoint:

http://127.0.0.1:8000/api/health

Questions endpoint:

http://127.0.0.1:8000/api/v1/questions

Admin login:

http://127.0.0.1:8000/admin/login

## Flutter Setup

```powershell
cd frontend
flutter pub get
```

Create/import an Android Virtual Device named `Pixel_8` once in Android Studio
Device Manager. After that, the repository launcher starts it (when needed),
waits for Android to finish booting, and selects its actual device ID.

The Android emulator communicates with the Windows host through:

http://10.0.2.2:8000/api

## Normal Development Workflow

Terminal 1 - Backend:

```powershell
cd backend
php artisan serve
```

Terminal 2 - Mobile app (from the repository root):

```powershell
.\scripts\run-mobile.ps1
```

If PowerShell reports "running scripts is disabled on this system", enable
local scripts once for your user account:

```powershell
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
```

The launcher reuses a running Android emulator or starts `Pixel_8`, waits up to
three minutes for both Android and Flutter to report it ready, and then supplies
the emulator API address automatically. It never wipes or recreates an AVD.

Flutter shortcuts:
- `r` = hot reload
- `R` = hot restart
- `q` = quit

### Running from VS Code

The Android emulator is the only configured run target. Press `F5` (or pick
**SheZen Harmony (Android emulator)** in the Run and Debug panel) and VS Code
will boot/reuse `Pixel_8` first, then attach the Flutter debugger with the
emulator API address already set. No device picker, no Chrome or Windows
desktop target.

To keep `flutter run` on the command line from ever offering Chrome or Windows
desktop either, disable those targets once on your machine:

```powershell
flutter config --no-enable-web --no-enable-windows-desktop
```

## Project Structure

- `frontend/lib/` = Flutter application development
- `backend/app/` = Laravel models/controllers
- `backend/routes/api.php` = REST API routes
- `backend/database/migrations/` = schema changes
- `backend/database/seeders/` = development seed data
- `docs/` = technical documentation

Architecture references:

- `docs/ARCHITECTURE.md` = runtime and privacy boundaries
- `docs/PROJECT_STRUCTURE.md` = source ownership and folder conventions
- `docs/API_STARTER.md` = current API overview
- `docs/REQUIREMENTS_AUDIT.md` = implemented, partial, and missing MVP scope

## Important Security Notes

- Never commit `backend/.env`
- Never commit passwords
- Never commit Firebase/private service keys
- Each developer uses their own local database credentials
- `.env.example` contains placeholders only

## Current Project Status

The base stack is working:

Flutter → Laravel API → MySQL

Current starter capabilities:
- Health endpoint
- Development questionnaire data
- Intervention database foundation
- Flutter API connectivity test

The demo questionnaire content is not the client-approved final stress assessment.
