# SheZen Harmony — Development Starter

This starter is designed for the SheZen capstone project.

## Target stack

- Flutter / Dart — student mobile app
- Laravel 13 — REST API + web-admin backend
- Laravel Sanctum — mobile/API authentication (installed during bootstrap)
- MySQL — application database
- Firebase Analytics / Google Analytics — added after the local stack is working
- Git + GitHub — team version control
- VS Code — main editor
- Android Studio / Pixel emulator — Android testing

## Important

This ZIP does **not** contain passwords, API keys, Firebase configuration, Composer `vendor/`,
Flutter build output, or generated Laravel secrets.

The first setup script creates fresh Laravel 13 and Flutter projects on your computer and
then copies the SheZen starter files into them.

---

# Recommended folder

Extract this ZIP somewhere simple, for example:

`C:\Development\SheZenHarmony_Starter`

Avoid OneDrive-synced folders and very long paths while you are getting the project working.

---

# Step 1 — Open the workspace

In VS Code:

1. File → Open Workspace from File
2. Open `SheZenHarmony.code-workspace`

Or open the extracted folder directly.

---

# Step 2 — Check your computer

Open a **PowerShell** terminal in VS Code and run:

```powershell
.\scripts\check-environment.ps1
```

You need these commands to work:

```powershell
php -v
composer -V
flutter --version
git --version
```

For Android development you also need Android Studio / Android SDK configured.

MySQL is required before you run the database migrations.

---

# Step 3 — Create the actual Laravel + Flutter projects

Run:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\bootstrap.ps1
```

This script will:

1. Create `backend/` as a fresh Laravel 13 application.
2. Install Laravel API support / Sanctum.
3. Copy the SheZen API starter into Laravel.
4. Create `frontend/` as a Flutter Android + iOS application.
5. Add the Flutter `http` package.
6. Copy the SheZen starter UI/API service into Flutter.
7. Configure Android debug builds to allow the local HTTP development server.

After this completes, you will have:

```text
SheZenHarmony_Starter/
├── backend/
├── frontend/
├── starter/
├── scripts/
└── SheZenHarmony.code-workspace
```

---

# Step 4 — Create the MySQL database

Open MySQL from a terminal:

```powershell
mysql -u root -p
```

Then create the database:

```sql
CREATE DATABASE shezen_harmony
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Check it:

```sql
SHOW DATABASES;
```

Exit:

```sql
exit;
```

If `mysql` is not recognized, MySQL may be installed but its `bin` folder is not in PATH.
You can also use MySQL Workbench to create the database.

---

# Step 5 — Connect Laravel to MySQL

Run:

```powershell
.\scripts\configure-database.ps1
```

The script will ask for:

- DB host (normally `127.0.0.1`)
- DB port (normally `3306`)
- DB name (`shezen_harmony`)
- DB username (`root` or preferably a dedicated development user)
- DB password

The password is stored only in your local `backend/.env` file, which is ignored by Git.

Then run:

```powershell
cd backend
php artisan migrate
php artisan db:seed
cd ..
```

---

# Step 6 — Run Laravel

From the project root:

```powershell
.\scripts\run-backend.ps1
```

Or manually:

```powershell
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

Test in your Windows browser:

`http://127.0.0.1:8000/api/health`

Expected JSON:

```json
{
  "status": "success",
  "service": "SheZen API",
  "message": "Laravel backend is connected."
}
```

---

# Step 7 — Run Flutter

Keep Laravel running.

Open another VS Code PowerShell terminal:

```powershell
cd frontend
flutter emulators
flutter emulators --launch Pixel_8
flutter devices
flutter run
```

The starter Flutter home page contains **Test API connection**.

For an Android emulator the default API address is:

`http://10.0.2.2:8000/api`

`10.0.2.2` is the emulator alias for the Windows host machine.

If you use a **physical Android phone**, do not use `10.0.2.2`.
Run Laravel on `0.0.0.0`, find your PC's LAN IP with `ipconfig`, and run Flutter with:

```powershell
flutter run --dart-define=API_BASE_URL=http://YOUR_PC_IP:8000/api
```

Your phone and PC must be on the same network.

---

# Current starter features

The starter intentionally contains only safe foundation work:

## Flutter
- SheZen home screen
- API connectivity test
- Placeholder navigation cards for:
  - Stress Check
  - Activities
  - Journal
- Central API configuration
- HTTP API service

## Laravel
- `/api/health`
- database models/migrations for:
  - stress questions
  - question options
  - stress assessments
  - stress responses
  - interventions
  - intervention usage
- starter seed data clearly marked as development/demo content

## Not yet implemented
These should be developed after client requirements are confirmed:

- final stress questionnaire
- final scoring algorithm and low/medium/high thresholds
- suicide-ideation/referral workflow
- production authentication rules
- anonymous-vs-identified data policy implementation
- admin management screens
- rule-based support flow
- notifications
- Firebase Analytics
- production hosting
- iOS release configuration

Do **not** treat the seeded demo questions as the client's approved questionnaire.

---

# Suggested development order

1. Environment working
2. Laravel → MySQL
3. Flutter → Laravel
4. Final database review
5. User/admin authentication
6. Admin questionnaire CRUD
7. Approved assessment/scoring logic
8. Mobile assessment workflow
9. Intervention management + recommendations
10. Anonymous session support
11. Progress/history
12. Rule-based support flow
13. Notifications
14. Analytics
15. QA/security/privacy testing
16. Deployment/iOS

---

# Git

After the project works:

```powershell
git init
git add .
git commit -m "Initial SheZen development foundation"
```

Before pushing, verify that `backend/.env` is **not** staged:

```powershell
git status
```

Never commit `.env`, Firebase private keys, passwords, or production credentials.
