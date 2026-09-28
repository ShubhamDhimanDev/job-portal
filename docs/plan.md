# HRMS / Recruitment Consultancy Portal — Build Plan

**Status:** approved, in progress
**Owner context:** Pradhi Associates — an HR consultancy. Client companies approach Pradhi manually (phone/email/in person), never through the portal. Pradhi's own staff ("super admin") then post the job on the portal on that company's behalf and collect candidates through it.

## 0. Where the codebase stood before this plan

Confirmed by direct exploration (2026-09-28): this is the stock `laravel/react-starter-kit` (Laravel 13 + Inertia v3 + React 19 + Fortify) with **one finished page** — the marketing homepage (`resources/js/pages/home.tsx` + `resources/js/layouts/theme-layout.tsx`), branded for "Pradhi Associates". Everything else needed for a job portal — Jobs, Companies, Candidates, Applications, roles, admin panel, Excel export, email sending — **does not exist yet**. Auth (Fortify) is installed but not wired to any route or page: there is currently no way to log in. `routes/web.php` has exactly one route. This is a from-scratch build, not a refactor.

One landmine worth flagging: `database/migrations/0001_01_01_000002_create_jobs_table.php` already exists — but it's **Laravel's queue jobs table**, unrelated to job postings. Our job-posting model/table is named `JobPosting` / `job_postings` throughout to avoid any collision.

## 1. Product decisions (confirmed with user)

| Decision | Choice |
|---|---|
| Candidate accounts | None. Public guest apply form per job (name, email, phone, resume, optional note). No candidate login/dashboard. |
| Internal staff roles | Single `super_admin` role for now. Plain `role` string column on `users` (no spatie/permission package) — future-proofed for a second role later without a redesign. |
| New Composer packages | `maatwebsite/excel` only (for real .xlsx export). No `spatie/laravel-permission`. |
| Email sending | Build the Mailable + to/cc/bcc admin UI now; `MAIL_MAILER` stays on Laravel's default (`log`) until real SMTP/SES/Postmark/Resend credentials are added to `.env` later — that's a credentials/config change, not a code change. |

## 2. Research: what a recruitment-consultancy job portal needs

Looked at current (2026) recruitment/staffing client-portal and job-portal-admin patterns. Relevant takeaways applied below, sources at the end:

- Admin dashboards center on **status at a glance**: active jobs, new/shortlisted/on-hold candidates — not just a flat candidate list. We add a lightweight `status` field on applications (New / Shortlisted / On Hold / Rejected / Hired) so "get candidates list" is actually usable for triage, not just a dump. This is a small addition beyond the literal 4 asks but is what makes (b) and (c) useful in practice.
- Agencies routinely post one role while "representing" a client company that never touches the system — this validates the `Company` record being a simple **internal reference record** (name + contact info + notes), not a company user account.
- Candidate-pipeline visibility, mass actions (bulk export/email), and filtered exports (by job, by status, by date range) are the standard shape of "export candidates" in this space — we filter the Excel export the same way the candidate list is filtered, not just "export everything."
- Mobile-first, keyword+location+type filtering, and clear job-type/salary/deadline metadata are baseline expectations on the public job-listing side.

Sources: [People Managing People — 2026 recruiting software features](https://peoplemanagingpeople.com/recruitment/recruiting-software-features/), [ATZ CRM — recruitment client portal](https://atzcrm.com/blog/recruitment-client-portal/), [Staffing Future — client portal features](https://www.staffingfuture.com/client-portal-features-that-streamline-high-volume-hiring-periods/), [RecruitersLineup — client portal software](https://www.recruiterslineup.com/top-client-portal-software-for-recruitment-and-staffing-agencies/), [Voizac — job portal features 2026](https://www.voizac.com/blog/top-features-every-modern-job-portal-should-have-in-2026)

## 3. Design system decision (item 1: "convert other pages to homepage design")

There are no other real pages yet, so this becomes: **which design system does each new area of the app use.** `resources/css/app.css` already anticipates the answer — it defines the homepage's bold gold/ink brand palette (`--color-brand-gold #eec132`, `--color-brand-amber #ffe079`, `--color-brand-ink #1a1a1a`, `--color-brand-cream #fffaf0`) as `@theme` tokens available everywhere, **and** a neutral shadcn/ui oklch palette scoped specifically to a `.admin-layout` wrapper class, with a code comment stating this is intentional so "public marketing pages can use their own brand palette instead of the neutral admin theme tokens." A full unused Radix/shadcn component library (`resources/js/components/ui/*`, including `sidebar.tsx`) already sits ready for exactly this purpose.

Decision — follow the codebase's own intent:
- **Public-facing pages** (home, job listing, job detail, apply form, login) use the **gold/ink brand system**: same header/footer chrome as `theme-layout.tsx`, same `Reveal`/`framer-motion` treatment, same card/section patterns, `lucide-react` icons, bold type scale. Job listing and job detail extend `ThemeLayout` so they're visually inseparable from the homepage.
- **Admin/dashboard pages** use the **neutral shadcn tokens** inside a new `admin-layout.tsx` wrapped in the existing `.admin-layout` class, built from the already-installed Radix `components/ui/*` primitives (sidebar, table-ish patterns via card/badge/dropdown-menu, dialog for modals, sonner for toasts). This is a deliberate, standard "marketing site vs. app shell" split, not an inconsistency — an admin tool with 6pt-gold pill buttons and framer-motion parallax would actively hurt usability for a data-entry/table-heavy workflow.

## 4. Data model

New enums (`app/Enums/`, PHP backed enums, TitleCase keys per project convention):
- `UserRole`: `SuperAdmin = 'super_admin'`
- `EmploymentType`: `FullTime`, `PartTime`, `Contract`, `Internship`
- `WorkMode`: `OnSite`, `Remote`, `Hybrid`
- `JobStatus`: `Draft`, `Published`, `Closed`
- `ApplicationStatus`: `New`, `Shortlisted`, `OnHold`, `Rejected`, `Hired`

New/changed tables:

**`users`** (migration adds a column) — `role` string, default `super_admin`.

**`companies`** — the client company a job is posted on behalf of. `id`, `name`, `contact_person` nullable, `contact_email` nullable, `contact_phone` nullable, `website` nullable, `notes` text nullable (internal notes, since the relationship is offline/manual), timestamps.

**`job_postings`** — `id`, `company_id` FK→companies (restrict on delete), `posted_by_id` FK→users, `title`, `slug` unique, `description` longtext, `responsibilities` text nullable, `requirements` text nullable, `location`, `work_mode` (enum), `employment_type` (enum), `experience_level` string nullable, `min_salary`/`max_salary` int nullable, `salary_negotiable` bool default false, `department` string nullable, `vacancies` int default 1, `status` (enum, default `draft`), `application_deadline` date nullable, timestamps.

**`job_applications`** — `id`, `job_posting_id` FK→job_postings (cascade delete), `name`, `email`, `phone`, `resume_path` string, `cover_note` text nullable, `status` (enum, default `new`), `admin_notes` text nullable, timestamps. Unique on (`job_posting_id`, `email`) — one application per email per job.

Resumes are stored on the **`local`** (private) disk, never `public` — admin downloads them via a signed/authenticated controller action, not a public URL.

## 5. Routes

```
routes/web.php            home route + requires the files below
routes/public.php         GET /jobs, GET /jobs/{jobPosting:slug}, POST /jobs/{jobPosting:slug}/apply
routes/auth.php           GET/POST /login, POST /logout   (guest/auth middleware as appropriate)
routes/admin.php          prefix admin, name admin., middleware auth — /dashboard only, then requires:
routes/admin_jobs.php     admin. jobs + companies CRUD (own file, own route group)
routes/admin_candidates.php   admin. candidates list/status/export/email (own file, own route group)
```
Splitting admin routes into three separate files (not one shared `admin.php`) is deliberate: it lets the jobs/companies slice and the candidates slice be built in parallel with zero risk of two agents editing the same route file at the same time.

Fortify's `home` redirect (`config/fortify.php`) points to `/admin/dashboard`. No public registration route is exposed — admin accounts are created via seeder/tinker by whoever holds server access, matching "single internal staff role, not self-service."

## 6. Feature breakdown

### Public front pages
- **Job listing** `/jobs` — search by keyword, filter by location/employment type/work mode, only `published` postings, paginated, brand-styled cards.
- **Job detail** `/jobs/{slug}` — full description, company name (not contact info), meta (location, type, salary if not hidden, deadline, vacancies), an inline apply form (name/email/phone/resume upload/optional note), client + server validation (resume: pdf/doc/docx, ≤5MB).
- Homepage nav gets a "Careers"/"Jobs" link pointing at `/jobs`; homepage hero CTA can also point there.

### Super admin — job posting on behalf of companies (2a)
- Company picker on the job form: select an existing `Company` or create one inline (name + optional contact info) — this is what "on behalf of other companies" means operationally, since companies never log in themselves.
- Job CRUD: create/edit as `draft`, `publish`, `close`, duplicate-as-new-draft (handy for repeat roles from the same client). List view with status filter + search.

### Super admin — candidates (2b)
- Global candidates list across all jobs + per-job candidates list, with filters: job, status, date range, search (name/email/phone).
- Inline status update (New → Shortlisted / On Hold / Rejected / Hired) + admin notes per candidate.
- Resume download (authenticated, streamed from private disk).

### Super admin — Excel export (2c)
- `maatwebsite/excel` export class, columns: Job Title, Company, Candidate Name, Email, Phone, Status, Applied Date, Resume filename. Respects the **same filters** currently applied on the candidates list (job/status/date range/search) — "export what I'm looking at," not always-everything.

### Super admin — email the export (2d)
- Dialog on the candidates page: **To** (required, supports multiple), **Cc**, **Bcc** (optional, multiple), subject (prefilled), message body (prefilled, editable). Generates the same filtered .xlsx, attaches it, sends via a `CandidatesExportMail` Mailable. Uses Laravel's mail config as-is; nothing is sent for real until the user adds SMTP/SES/etc. credentials to `.env` — until then it lands in the `log` channel, which is fine for now per the user's choice above.

## 7. Build phases & agent assignment

**Phase 0 — Foundation (done directly, not delegated — everything below depends on it, so it must land first and coherently):**
`git init` + baseline commit, `composer require maatwebsite/excel`, enums, migrations, models + relationships, factories, seeder update, route file skeletons, login (`AuthenticatedSessionController` + brand-styled `resources/js/pages/auth/login.tsx`), `admin-layout.tsx` shell (sidebar nav + topbar + logout) with a real `DashboardController` (counts), and placeholder "coming soon" admin pages for jobs/companies/candidates so the app is buildable/runnable at every checkpoint.

**Phase 1 — three independent, parallel agents (no shared files, per the route-file split above):**
1. **Public job board agent** — `PublicJobController`, `routes/public.php`, `resources/js/pages/jobs/index.tsx` + `jobs/show.tsx`, apply-form validation, nav link on `theme-layout.tsx`/`home.tsx`. Brand design system.
2. **Admin jobs & companies agent** — `routes/admin_jobs.php`, `JobPostingController` + `CompanyController` (admin namespace), `resources/js/pages/admin/job-postings/*`, `resources/js/pages/admin/companies/*`. Neutral admin design system.
3. **Admin candidates + export + email agent** — `routes/admin_candidates.php`, `JobApplicationController` (admin namespace), candidates list/filter/status UI, `CandidatesExport` class, `CandidatesExportMail`, the to/cc/bcc dialog.

Each agent writes/updates Pest feature tests for its own slice and runs them before finishing (per this project's test-enforcement rule). After all three land, a final pass wires the placeholder "coming soon" links into the real pages if anything was missed, runs `vendor/bin/pint --dirty`, `npm run lint`/`types:check`, and the full test suite.

## 8. Assumptions / open items to revisit later (not blocking)

- No spam protection (captcha/honeypot) on the public apply form yet — fine for a first version, flag if abuse becomes an issue.
- No email verification/duplicate-resume dedup beyond the one-application-per-email-per-job unique constraint.
- No client-company self-service portal (explicitly out of scope — companies interact with Pradhi manually, per the brief).
- Excel export runs synchronously (fine at current expected volume); move to a queued export job later if candidate volumes get large.
