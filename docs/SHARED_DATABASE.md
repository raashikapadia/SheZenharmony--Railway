# Shared team database (Aiven MySQL)

The team uses **one hosted MySQL database** so everyone sees the same rows —
questionnaires, quizzes, journal entries, users — instead of each laptop having
its own private copy. It runs on Aiven's free tier (MySQL 8.4, always on).

Git still only carries **code**. The database carries **data**. Pulling a branch
never changes the data; connecting to the shared database does.

## 1. Point the backend at it

Edit `backend/.env` (create it from `backend/.env.example` if you don't have one)
and set these lines. Ask a teammate for the password — it is **never** committed
to Git.

```dotenv
APP_KEY=<ask the team — must be identical for everyone, see §6>

DB_CONNECTION=mysql
DB_HOST=mysql-efa3d1-shezenharmony.j.aivencloud.com
DB_PORT=25998
DB_DATABASE=defaultdb
DB_USERNAME=shezen_dev
DB_PASSWORD="<ask the team>"
MYSQL_ATTR_SSL_CA=certs/aiven-ca.pem
```

Developers use the `shezen_dev` account. It can read and write every table in
`defaultdb` but cannot create users, change the service, or reach anything
else. The `avnadmin` master account is held by the project owner only.

`MYSQL_ATTR_SSL_CA` points at the public CA certificate in
`backend/certs/aiven-ca.pem` (already in Git). It forces the connection to use
TLS so nothing travels across the internet in plain text. The path is relative
to `backend/`, so the same line works on Windows, macOS and Linux.

Then, from `backend/`:

```powershell
php artisan config:clear
php artisan db:show      # should print Host = mysql-efa3d1-... and Database = defaultdb
php artisan serve
```

If `db:show` still says `127.0.0.1`, your `.env` didn't save or you have a
cached config — run `php artisan config:clear` again.

## 2. Connect in MySQL Workbench

**Database → Manage Connections → New**

| Tab / field            | Value                                          |
| ---------------------- | ---------------------------------------------- |
| Connection Name        | SheZen shared (Aiven)                          |
| Connection Method      | Standard (TCP/IP)                              |
| Hostname               | `mysql-efa3d1-shezenharmony.j.aivencloud.com`  |
| Port                   | `25998`                                        |
| Username               | `shezen_dev`                                   |
| Password               | *Store in Vault…* → the team password          |
| Default Schema         | `defaultdb`                                    |
| **SSL tab → Use SSL**  | **Require**                                    |
| SSL CA File            | `<your clone>\backend\certs\aiven-ca.pem`      |

Click **Test Connection**. It should say "Successfully made the MySQL
connection" with SSL. Workbench may warn about the server version — that's fine.

## 3. Logins that already exist

The shared database is a full copy of Siliva's local database taken on
2026-09-16, migrated up to `fix/journaling`. It contains everything that was
there: the 74-question SheZen Wellbeing Questionnaire (published and active),
all questionnaires and their audit history, completed assessments and
responses, roles, helplines, personal guidance, chatbot content, wellbeing
activities, the registered student accounts, and the demo accounts from
`DevelopmentSeeder`:

| Role    | Email                             | Password       |
| ------- | --------------------------------- | -------------- |
| Admin   | `admin.demo@shezen.local`         | `Admin1234!`   |
| Student | `student.demo@student.usp.ac.fj`  | `Student1234!` |

Student accounts registered on *your* laptop are not here — register again
through the app once you're connected. From now on, anything anyone creates
(quizzes, journal entries, questionnaire edits) is visible to the whole team.

## 4. Rules for a shared database

Everyone is now editing the **same** data, so:

- **Never run `php artisan migrate:fresh`, `migrate:reset`, `migrate:rollback`
  or `db:wipe` while pointed at the shared DB.** It deletes everyone's data.
  If you need a scratch DB, switch `.env` back to your local MySQL first.
- Plain `php artisan migrate` is fine — it only adds new tables/columns from
  migrations in your branch. Tell the team in chat when you run one, because
  a migration from your branch may add columns that other branches don't know
  about yet.
- `php artisan db:seed` is safe — every seeder is guarded and does nothing once
  its data exists.
- Don't drop or truncate tables in Workbench.
- The Aiven free tier allows a limited number of simultaneous connections. If
  you get "Too many connections", close idle Workbench tabs.

## 5. Who can see the data — and keeping it that way

The data is private to the development team. What enforces that:

1. **Credentials.** Nothing is readable without a database password. The
   `shezen_dev` and `avnadmin` passwords are shared **person to person only**
   (in a DM, never in a group chat, never in Git, never in a screenshot).
   `backend/.env` is gitignored for exactly this reason — check `git status`
   before every commit and never force-add it.
2. **TLS is mandatory.** Both accounts are created with `REQUIRE SSL`, so the
   server rejects any unencrypted login even if someone misconfigures their
   client. The public CA in `backend/certs/aiven-ca.pem` lets clients verify
   they're talking to *our* server and not an impostor.
3. **Least privilege.** Developers use `shezen_dev`, which is confined to
   `defaultdb`. Only the project owner holds `avnadmin`.
4. **Encryption at rest.** Diary titles and pages are encrypted with the
   Laravel `APP_KEY` before they're written. Even someone with the database
   password sees only ciphertext unless they also have `APP_KEY`. Aiven also
   encrypts the disks and backups.
5. **IP allow-list (optional, owner-controlled).** In the Aiven console →
   service → *Overview* → *Allowed IP addresses*, the owner can restrict
   connections to specific addresses. Home/campus/mobile IPs change often, so
   this is only practical if everyone is on a stable network — otherwise leave
   it open and rely on 1–4.
6. **Aiven console.** Only the owner's Aiven login can see, power off, delete,
   or reset the service. Don't share that login; if a second admin is needed,
   invite them as a project member instead.

**If someone leaves the team** or a password is ever exposed: the owner
resets `shezen_dev`'s password in Workbench (`ALTER USER 'shezen_dev'@'%'
IDENTIFIED BY '<new>'`) and shares the new one. Nothing else needs to change.

## 6. Everyone must use the same `APP_KEY`

Because diary content is encrypted with `APP_KEY`, a developer with a
different key will get *"The payload is invalid"* errors when opening entries
written by someone else. Get the team's `APP_KEY` from the owner and put it in
your `.env`. **Do not run `php artisan key:generate`** on a machine pointed at
the shared database — it would make every existing diary entry unreadable.

## 7. Switching back to a local database

Keep your old local settings around (e.g. in `backend/.env.local-backup`,
which is gitignored) and swap the `DB_*` lines back when you want to test
something destructive. Run `php artisan config:clear` after every `.env` change.

## 8. OTP inbox (Mailpit)

Student registration and login send a one-time code by email. The project uses
**Mailpit** for this everywhere — local work and the team demo alike. There is
no hosted inbox: each person runs Mailpit on their own machine and reads the
codes their own backend sends.

The database is shared; the mail is not. Mailpit only catches mail sent from the
machine it runs on, so when you trigger an OTP the code arrives in *your* inbox
at <http://127.0.0.1:8025>. During a demo, whoever is driving the laptop reads
the code from their own Mailpit.

Install it once:

```powershell
winget install axllent.mailpit
```

Keep these lines in `backend/.env` on every machine, shared database or not:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@shezen.local"
```

Then `php artisan config:clear`. Mailpit has to be running before anyone
registers or logs in — the backend opens a real SMTP connection to port 1025 and
the request fails if nothing is listening. Run
`backend/scripts/mailpit-autostart.ps1` once to have it start with Windows and
keep its inbox between reboots.
