# Plan: Add candidates singly or in bulk (CSV/XLSX + resume ZIP)

Goal: let admins add a candidate for a chosen job with a resume, either one at a time or in bulk, without going through the public job board form.

## Current state
- `job_applications` has `phone` and `resume_path` as NOT NULL, with a unique index on `(job_posting_id, email)`.
- `JobBoardController::apply()` stores the resume at `resumes/{jobId}/{uuid}.ext` on the `local` disk, creates the `JobApplication`, and dispatches `RateCandidateApplication`.
- `maatwebsite/excel` is installed and the PHP `zip` extension is available, so no new dependencies are needed.
- Admin candidates page: `resources/js/pages/admin/candidates/index.tsx`. Routes: `routes/admin_candidates.php`.

## Backend

### 1. Shared action: `app/Actions/Candidates/CreateCandidateApplication`
- Takes a `JobPosting`, candidate fields, and a resume (`UploadedFile` or local file path).
- Stores the resume with the same path scheme as `apply()`, creates the row, dispatches `RateCandidateApplication`.
- Handles duplicates (same email + job): rejected for single add, skipped and reported for bulk.
- Refactor `JobBoardController::apply()` to use it, so the public form, single add and bulk import share one code path. Existing public tests must still pass.

### 2. Single add
- Routes: `GET admin/candidates/create`, `POST admin/candidates`.
- `JobApplicationController::create` / `store` with `AdminCandidateStoreRequest`.
- Rules match the public form: name, email, phone, existing `job_posting_id`, resume PDF/DOC/DOCX up to 5 MB, email unique per selected job.
- Optional: a `source` field (public / admin / import) via a small migration.

### 3. Bulk import
- Routes: `GET admin/candidates/import` (page), `GET admin/candidates/import/template` (sample CSV), `POST admin/candidates/import`.
- `CandidateImportController` + `ImportCandidatesRequest`: spreadsheet (`csv,xlsx`) required, ZIP of resumes optional, size caps on both.
- On upload, store both files in temp storage and dispatch a queued import; rows are not processed in the web request.
- Spreadsheet columns: `name, email, phone, job, resume_filename`. `job` accepts a slug or ID. A missing `resume_filename` is a row error because `resume_path` is NOT NULL.

### 4. Import class: `app/Imports/CandidatesImport`
- `ToCollection`, `WithHeadingRow`, `WithChunkReading`, queued.
- Per-row checks: valid fields, job resolves, resume exists in the extracted ZIP (case-insensitive filename match), extension PDF/DOC/DOCX, size <= 5 MB, not a duplicate.
- Valid rows go through the shared action. Invalid rows are collected with row number and reason.
- ZIP safety: extract to a temp dir, use only the basename of each entry (zip-slip guard), skip non-PDF/DOC/DOCX files, cap total file count.
- Delete temp files when the import finishes or fails.

### 5. Result reporting
- New `candidate_imports` table (user, status, total, created, skipped, failed, error report JSON) with migration, model and factory.
- On completion the admin sees a summary and can download failed rows as CSV. The import page polls or reloads for progress.
- Lighter alternative: skip the table and use a flash/email summary (loses history).

### 6. AI rating
- Each created candidate dispatches `RateCandidateApplication`, as on the public form.
- Add an "AI rate imported candidates" checkbox (default on) to control API cost and rate limits on large imports.

## Frontend (Inertia + React, Wayfinder routes)
- `admin/candidates/create.tsx`: job dropdown, name, email, phone, resume file input.
- `admin/candidates/import.tsx`: spreadsheet and ZIP inputs, template download link, instructions, results panel with failed-row download.
- `admin/candidates/index.tsx`: add "Add candidate" and "Import" buttons.
- Follow existing admin page styling and components.

## Tests (Pest, feature)
- Single add: success stores the file, creates the row and dispatches the rating job (Queue and Storage faked); validation failures including bad file type and duplicate email on the same job; unauthenticated redirect.
- Bulk import: valid CSV + ZIP creates the right rows and files; missing resume, unknown job, bad email and duplicate each produce a row error; zip-slip entries ignored; AI rating checkbox on/off; summary counts and failed-rows CSV.
- Regression: public `apply` tests still pass after the refactor.
- Finish with `vendor/bin/pint --dirty --format agent` and run the affected tests.

## Build order
1. Extract `CreateCandidateApplication`, refactor `apply()`, run public tests.
2. Single add: backend, page, button, tests.
3. Import: migration/model, import class, controller, template download, tests.
4. Import page, results UI, final polish.

## Open decisions
1. Keep a `candidate_imports` history table, or flash/email summary only? (Recommended: table.)
2. AI rating on import: default on or off?
3. Duplicates: skip and report, or update the existing candidate? (Recommended: skip.)
4. Is `phone` required for admin-added candidates? Allowing blank needs a migration, since the column is NOT NULL.
5. Add a `source` column to track how each candidate arrived?
