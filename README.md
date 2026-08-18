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

Launch emulator:

```powershell
flutter emulators
flutter emulators --launch Pixel_8
flutter devices
```

Run:

```powershell
flutter run
```

The Android emulator communicates with the Windows host through:

http://10.0.2.2:8000/api

If you use a different backend port, run Flutter with the device ID shown by `flutter devices`:

```powershell
flutter run -d emulator-5554 --dart-define=API_BASE_URL=http://10.0.2.2:8001/api
```

Replace `emulator-5554` with the actual device ID from your machine.

## Normal Development Workflow

Terminal 1:

```powershell
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

Terminal 2:

```powershell
cd frontend
flutter run
```

Flutter shortcuts:
- `r` = hot reload
- `R` = hot restart
- `q` = quit

## Project Structure

- `frontend/lib/` = Flutter application development
- `backend/app/` = Laravel models/controllers
- `backend/routes/api.php` = REST API routes
- `backend/database/migrations/` = schema changes
- `backend/database/seeders/` = development seed data
- `docs/` = technical documentation

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
