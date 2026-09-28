# AI Resume Parsing + Candidate Fit Rating — Implementation Plan

**Status:** planned, not started (research turns only so far — see conversation history / `docs/plan.md` for the base HRMS build this extends)

## 1. Goal

For every candidate application: parse the uploaded resume into structured profile data, and rate how well that candidate fits the *specific job they applied to*, 1–10, with reasoning — so the super admin's candidates list can be sorted best-fit-first instead of just chronologically.

## 2. Why `laravel/ai`

Confirmed via research (not guessed): `laravel/ai` went GA (v1.0.0, 2026-09-23) as a first-party package shipped alongside Laravel 13 — the exact version this app runs. It natively supports Anthropic and Gemini as providers, reads file attachments straight from Laravel `Storage` disks, and returns provider-validated structured JSON via a schema — which covers this whole feature with one Agent class and one API call per application, no hand-rolled HTTP/SDK code needed.

**One call does both jobs.** A single agent schema can nest an extracted `profile` object and a `rating` object in one response — so we get parsed resume data *and* the fit score from one request, not two.

## 3. Provider strategy: Gemini now, swap later without code changes

Per your instruction, we test against a Gemini key first, then move to whichever provider/model you settle on with the client (likely Anthropic, but not committed). To make that swap a config change, not a code change, the agent will **not** hardcode a provider via the `#[Provider(...)]` attribute. Instead it reads provider + model from our own two settings at call time:

```
# .env
GEMINI_API_KEY=
CANDIDATE_RATING_AI_PROVIDER=gemini
CANDIDATE_RATING_AI_MODEL=<confirm current Gemini model id — see §8>
```

```php
// config/services.php
'candidate_rating' => [
    'provider' => env('CANDIDATE_RATING_AI_PROVIDER', 'gemini'),
    'model' => env('CANDIDATE_RATING_AI_MODEL'),
],
```

Later, switching to the client's chosen provider is:
```
ANTHROPIC_API_KEY=...
CANDIDATE_RATING_AI_PROVIDER=anthropic
CANDIDATE_RATING_AI_MODEL=claude-sonnet-5
```
plus `php artisan config:cache` — nothing else changes.

## 4. New dependencies (need your approval per project rules — `laravel/ai` you've already named; the second one is new)

- **`laravel/ai`** — the SDK itself. You've explicitly asked for this one.
- **`phpoffice/phpword`** — Claude/Gemini document-understanding attachments are PDF/image-native; DOCX isn't a supported attachment type on any provider I've found documented. For `.doc`/`.docx` resumes (our apply form already accepts these), we extract plain text with PHPWord and send it as prompt text instead of an attachment, so DOCX applicants get rated too, not silently skipped. Flagging this explicitly since it wasn't named in your message — let me know if you'd rather scope v1 to PDF-only and skip this dependency.

## 5. Data model

Add columns to the existing `job_applications` table (one evaluation per application — it's already scoped to one specific job, which is exactly what "fit rating" needs, so no new table; see [docs/plan.md](plan.md) for why the rest of the schema looks the way it does):

- `ai_status` string, default `pending` → new enum `App\Enums\AiRatingStatus` (`Pending`, `Processing`, `Completed`, `Failed`)
- `ai_score` unsigned tiny int, nullable (1–10)
- `ai_reasoning` text, nullable
- `ai_strengths` json, nullable (array of short strings)
- `ai_gaps` json, nullable (array of short strings)
- `ai_profile` json, nullable — the parsed *enrichment* fields the resume adds beyond what the apply form already captures (name/email/phone are already columns): skills, total experience, education history, work history, certifications, languages, a short summary
- `ai_rated_at` timestamp, nullable
- `ai_error` text, nullable — last failure message, shown to the admin so a failed rating isn't a silent black box

No history/versioning in v1 — re-rating overwrites the previous result. Easy to revisit if you want an audit trail later.

## 6. The Agent

`app/Ai/Agents/CandidateRatingAgent.php` — implements `Agent` + `HasStructuredOutput`.

- Constructor takes the `JobPosting`.
- `instructions()` composes a recruiter-screening prompt that embeds the job's title, description, responsibilities, requirements, employment type, and experience level as context.
- `schema()` (sketch — exact JsonSchema builder calls to be confirmed against the installed package, builder API is consistent across the docs I read):
  ```php
  return [
      'profile' => $schema->object(fn ($s) => [
          'skills' => $s->array()->items($s->string()),
          'total_experience_years' => $s->number(),
          'education' => $s->array()->items($s->object(fn ($s) => [
              'degree' => $s->string(), 'institution' => $s->string(), 'year' => $s->string()->nullable(),
          ])),
          'work_history' => $s->array()->items($s->object(fn ($s) => [
              'company' => $s->string(), 'title' => $s->string(),
              'start' => $s->string()->nullable(), 'end' => $s->string()->nullable(),
          ])),
          'certifications' => $s->array()->items($s->string()),
          'summary' => $s->string(),
      ])->required(),
      'rating' => $schema->object(fn ($s) => [
          'score' => $s->integer()->min(1)->max(10)->required(),
          'reasoning' => $s->string()->required(),
          'strengths' => $s->array()->items($s->string()),
          'gaps' => $s->array()->items($s->string()),
      ])->required(),
  ];
  ```
- Called as:
  ```php
  (new CandidateRatingAgent($application->jobPosting))->prompt(
      'Evaluate this candidate for the attached job.',
      attachments: $isPdf
          ? [Files\Document::fromStorage($application->resume_path, disk: 'local')]
          : [], // DOCX text appended into the prompt string instead — see §4
      provider: config('services.candidate_rating.provider'),
      model: config('services.candidate_rating.model'),
  );
  ```

## 7. Trigger flow

- `app/Jobs/RateCandidateApplication.php` (queued): sets `ai_status = Processing`, calls the agent, writes the result fields on success, or `ai_status = Failed` + `ai_error` on exception (job retries/backoff handle transient failures; permanent failures stay visible to the admin).
- Dispatched from `JobBoardController::apply()` right after the application is created — a one-line addition to that existing controller (`RateCandidateApplication::dispatch($application)`), so rating happens automatically and asynchronously, never blocking the candidate's request.
- Admin can also trigger it on demand: `POST /admin/candidates/{jobApplication}/rate` — for retries on failure, or for applications that predate this feature.

## 8. Admin UI changes

- Candidates list (`resources/js/pages/admin/candidates/index.tsx`, already built): add an "AI Score" column (badge, color-banded — e.g. green 8–10 / amber 5–7 / red 1–4 / gray pending-or-failed), a sort-by-score control (high→low, the client's actual ask), a status filter (pending/failed/completed), and a "Re-rate" action on failed rows.
- A per-candidate expand/dialog showing the full parsed profile plus rating reasoning/strengths/gaps.
- Optional, not required for v1: a small rollup on the job posting's admin page ("12 of 15 candidates rated, avg 6.4").

## 9. Testing

`laravel/ai` ships a first-class fake: `AI::fake(['response' => [...] ])` returns a canned structured response matching our schema shape, and `AI::assertSent(fn ($request) => ...)` lets us assert the right job/resume context was sent — so the whole flow is testable without hitting Gemini/Anthropic in CI. Plan: `tests/Feature/Jobs/RateCandidateApplicationTest.php` covering success (fields populated correctly), failure (status/error recorded, no partial writes), and the admin sort/filter/re-rate endpoints.

## 10. Phased build order

1. **Foundation** (small, sequential): migration + enum, `composer require laravel/ai` (+ `phpoffice/phpword` if approved), publish config, `.env`/`config/services.php` wiring, confirm the open items in §11 against the actually-installed package source.
2. **Agent + job**: `CandidateRatingAgent`, `RateCandidateApplication` job, wire the dispatch into `JobBoardController::apply()`.
3. **Admin surface**: score column/sort/filter/re-rate on the existing candidates page.
4. **Tests + verification**: `AI::fake()`-based Pest coverage, then one real end-to-end smoke test against your Gemini key before calling it done.

## 11. Open items to verify at implementation time (not guessed here on purpose)

- Exact current Gemini model ID string — Google's Gemini lineup has moved to a "3.x" generation as of now (3.8 Flash / 3.1 Pro among current names); I don't have a confirmed exact API model-id string to put in `.env.example` and won't fabricate one. First implementation step is checking `ai.google.dev/gemini-api/docs/models` for the exact current id.
- The provider-selection value's exact type (plain string like `'anthropic'`/`'gemini'` vs. an SDK enum such as the `Lab::Anthropic` shown in Laravel's docs) — will confirm against the installed package's actual classes rather than assume.
- `Files\Document::fromStorage()`'s exact signature for specifying a non-default disk (our resumes live on the `local` private disk, not the package's default) — will confirm against source.
- Whether `AI::fake()` has a direct "simulate a thrown exception" mode for testing the failure path, or whether that's better tested by mocking the Agent class directly — will confirm once the package is installed and its test helpers are inspectable.

None of these affect the overall architecture above — they're implementation-detail confirmations, not open design decisions.

## 12. Cost (recap from earlier research, unchanged)

Resumes are short; a single rating call is roughly a few thousand input tokens (resume + job description + schema) and a few hundred output tokens. At current published rates this lands well under a cent per candidate on Sonnet-tier models, and Gemini's Flash-tier pricing is in a similar low range — negligible at this app's expected volume either way.

---

Not implementing until you give the go-ahead. Let me know if you want to adjust the automatic-on-apply trigger (vs. admin-triggered only), the PDF-vs-DOCX scope, or anything in the schema shape above, and I'll deploy agents to build this the same way as the base HRMS build.
