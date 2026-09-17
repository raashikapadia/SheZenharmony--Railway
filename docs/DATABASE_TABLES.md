# Database tables

What each table in the SheZen Harmony database holds, grouped by feature.
Row counts are from the shared team database on 2026-09-16 and will drift;
the descriptions won't. For the schema itself see
`backend/database/migrations/`; for the shared-database setup see
`SHARED_DATABASE.md`.

## Accounts and identity

| Table | Rows | Contains |
| --- | ---: | --- |
| `users` | 6 | Login accounts: email, hashed password, role, account status / hold. `name` is NULL for students by design — see `student_identities`. |
| `roles` | 4 | Role definitions (admin, student, …). |
| `user_roles` | 6 | Which user has which role. |
| `student_identities` | 5 | A random pseudonymous UUID per student plus the short SheZen ID they see (`shezen_code`, e.g. `SZ7K42P` — unique, fixed for life). Wellbeing data (diary, assessments, favourites) links to **this**, not to `users`, so it cannot be tied back to an email without the join. |
| `user_profiles` | 4 | Demographics collected at registration: year of study (plus `year_of_study_detail` when it is "Other"), gender, country, employment, relationship status, children, living situation, date of birth (must be 18+). |
| `student_consents` | 4 | Which survey-consent wording version each student agreed to, and when. Declining records nothing: the account is deleted instead. |
| `email_otp_challenges` | 65 | Login one-time codes. Only a **bcrypt hash** of the code is stored, plus purpose, device, expiry, attempts, verified / invalidated timestamps. To see a code during development, open Mailpit (`http://localhost:8025`). |
| `user_mfa_methods` | 5 | Encrypted MFA secrets and recovery codes per user. |
| `personal_access_tokens` | 33 | API tokens (Laravel Sanctum) issued to the mobile app after login. |
| `sessions` | 10 | Web sessions. |
| `password_reset_tokens` | 0 | Password-reset tokens (unused — login is OTP-based). |
| `anonymous_sessions` | 0 | For guests using the app without an account (schema only, not used yet). |

## Questionnaire and assessment

| Table | Rows | Contains |
| --- | ---: | --- |
| `questionnaires` | 2 | The questionnaire "containers": title, description, version, status (draft / published / archived), result scale, publish date. Only one is published at a time. |
| `questionnaire_sections` | 8 | The categories of a questionnaire (e.g. *Academic and Work-Related Well-Being*) with their weights and order. |
| `stress_questions` | 79 | Question text, type (scale, …), min/max score, reverse-scoring flag, stress relevance / direction / weight. |
| `question_options` | 395 | Answer choices per question — label, value, score, position (five per scale question). |
| `questionnaire_questions` | 79 | Join table: which question sits in which questionnaire and section, at what position, and whether it is required. |
| `stress_score_bands` | 7 | Result ranges (low / moderate / high …) with min–max scores and the message shown to the student. Can be per-questionnaire or per-section. |
| `questionnaire_audit_logs` | 17 | Admin change history for the questionnaire editor: who did what, with old and new values. |
| `stress_assessments` | 7 | One row per questionnaire a student completed: status, total score, overall raw score, resulting band. |
| `stress_responses` | 449 | Every individual answer in those assessments, with a snapshot of the question and option text at the time (so history survives later edits). |
| `category_results` | 48 | Per-section scores for each assessment: raw, min/max possible, percentage, weight, weighted score. |

## Support content (managed by admins)

| Table | Rows | Contains |
| --- | ---: | --- |
| `interventions` | 10 | Activities and messages offered after an assessment: breathing exercises, journaling prompts, "talk to someone", motivation lines. Has a content type, instructions, optional external link. |
| `intervention_recommendations` | 0 | Rules mapping a score band / section / result level to an intervention. |
| `intervention_usages` | 0 | When a student does an intervention, with mood before / after. Not populated yet. |
| `progress_entries` | 0 | Generic progress metrics over time per student. Not populated yet. |
| `personal_guidance` | 18 | Home-page content: short quotes (type *quote*) and step-by-step guidance articles (title, summary, when it helps, steps, duration). |
| `personal_guidance_favourites` | 11 | Which guidance items each student has favourited. |
| `personal_guidance_recommendations` | 33 | Rules mapping guidance items to score bands / sections. |
| `wellbeing_activities` | 2 | Video-based activities: title, description, video URL and type, category. |
| `helpline_resources` | 2 | Helplines for the Resources tab: name, organisation, phone(s), email, website, availability, emergency flag, display order. |
| `content_categories`, `tags`, `intervention_content_categories`, `intervention_tags` | 0 | Taxonomy for interventions. Schema exists; nothing uses it yet. |

## Diary and journaling

| Table | Rows | Contains |
| --- | ---: | --- |
| `diaries` | 1 | A student's diary: **encrypted** title, cover style, locked flag, client-side timestamps for sync. Soft-deletable. |
| `diary_pages` | 1 | Pages in a diary: **encrypted** title and body. Soft-deletable. |
| `diary_locks` | 1 | Salt and hash of the student's diary PIN — one lock per student. |
| `gratitude_entries` | 0 | Gratitude-journal entries: text plus a chosen symbol. |

Diary content is encrypted with the Laravel `APP_KEY`. Anyone using the
shared database must have the same `APP_KEY` or these rows are unreadable.
There is deliberately no admin route to diaries.

## Games and quizzes

| Table | Rows | Contains |
| --- | ---: | --- |
| `quizzes` | 0 | Quiz name, category, description, status. |
| `managed_quiz_questions` | 0 | Questions per quiz: text, options A–D, correct option, explanation, order. |
| `quiz_attempts` | 0 | A student's score per quiz attempt. |

## Chatbot and crisis handling

| Table | Rows | Contains |
| --- | ---: | --- |
| `chat_intents` | 15 | What the bot recognises: code, keywords, crisis flag, starter flag, priority. |
| `chat_responses` | 15 | The bot's reply for each intent. |
| `chat_quick_replies` | 27 | The tappable reply buttons shown under a response, and which intent each leads to. |
| `chat_sessions` | 0 | Conversations (student / anonymous / assigned support user, mode, status). Not stored yet. |
| `chat_messages` | 0 | Individual messages in a session, with flagging. Not stored yet. |
| `crisis_reports` | 0 | A flagged message escalated to a support person: severity, status, summary, who handled it. |

## Laravel plumbing (safe to ignore)

| Table | Contains |
| --- | --- |
| `migrations` | Which schema migrations have run (51). **Never edit by hand.** |
| `cache`, `cache_locks` | Application cache. |
| `jobs`, `job_batches`, `failed_jobs` | Queued background jobs (none in use). |

## Conventions worth knowing

- **Timestamps**: almost every table has `created_at` / `updated_at`.
  `deleted_at` means soft-delete — the row is hidden, not gone.
- **Snapshots**: `stress_responses` copies question and option text at
  answer time, so editing a question later doesn't rewrite history.
- **Student vs user**: anything a student *does* hangs off
  `student_identity_id`; anything about *logging in* hangs off `user_id`.
- **Seeders own some rows**: roles, the SheZen Wellbeing Questionnaire, the
  development stress assessment, helplines, personal guidance and chatbot
  content are created by `backend/database/seeders/`. Deleting them just
  means the next `php artisan db:seed` puts them back.
