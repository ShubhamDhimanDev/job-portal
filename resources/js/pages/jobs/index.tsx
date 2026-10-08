import { Form, Link } from '@inertiajs/react';
import { ArrowRight, Briefcase, Building2, MapPin, Search, SlidersHorizontal } from 'lucide-react';
import { index as jobsIndex, show as jobsShow } from '@/actions/App/Http/Controllers/JobBoardController';
import Reveal from '@/components/reveal';
import ThemeLayout from '@/layouts/theme-layout';

interface JobSummary {
    code: string;
    title: string;
    slug: string;
    company_name: string | null;
    location: string;
    department: string | null;
    work_mode: string;
    work_mode_label: string;
    employment_type: string;
    employment_type_label: string;
    salary_display: string | null;
    application_deadline: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface JobsPaginator {
    data: JobSummary[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

interface FilterOption {
    value: string;
    label: string;
}

interface JobsIndexProps {
    jobs: JobsPaginator;
    filters: {
        keyword: string;
        location: string;
        employment_type: string;
        work_mode: string;
    };
    employmentTypes: FilterOption[];
    workModes: FilterOption[];
}

const inputClasses =
    'w-full rounded-full border border-brand-ink/15 bg-white py-2.5 pr-4 pl-10 text-sm text-brand-ink placeholder:text-brand-ink/40 focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none';

const selectClasses =
    'w-full rounded-full border border-brand-ink/15 bg-white px-4 py-2.5 text-sm text-brand-ink focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none';

export default function JobsIndex({ jobs, filters, employmentTypes, workModes }: JobsIndexProps) {
    return (
        <ThemeLayout title="Careers – Pradhi Associates">
            <section className="bg-brand-cream py-20">
                <div className="mx-auto max-w-7xl px-6 lg:px-8">
                    <Reveal className="max-w-2xl">
                        <div className="flex items-center gap-3">
                            <span className="h-px w-8 bg-brand-gold" />
                            <span className="text-sm font-bold tracking-wide text-brand-gold sm:text-lg">
                                CAREERS
                            </span>
                            <span className="h-px w-8 bg-brand-gold" />
                        </div>
                        <h1 className="mt-2 text-4xl font-extrabold text-brand-ink sm:text-5xl">
                            Open Roles With Our Client Companies
                        </h1>
                        <p className="mt-3 text-lg text-brand-ink/60">
                            Pradhi Associates posts every live search here on behalf of the
                            companies we represent. Find a role and apply directly — no
                            account needed.
                        </p>
                    </Reveal>

                    <Reveal delay={100} className="mt-10">
                        <Form
                            {...jobsIndex.form()}
                            className="grid gap-4 rounded-3xl border border-brand-ink/10 bg-white p-6 shadow-xl shadow-black/5 sm:grid-cols-2 lg:grid-cols-5"
                        >
                            {({ processing }) => (
                                <>
                                    <div className="lg:col-span-2">
                                        <label
                                            htmlFor="keyword"
                                            className="mb-1.5 block text-sm font-bold text-brand-ink/70"
                                        >
                                            Keyword
                                        </label>
                                        <div className="relative">
                                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-brand-ink/40" />
                                            <input
                                                id="keyword"
                                                type="text"
                                                name="keyword"
                                                defaultValue={filters.keyword}
                                                placeholder="Job title or keyword"
                                                className={inputClasses}
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="location"
                                            className="mb-1.5 block text-sm font-bold text-brand-ink/70"
                                        >
                                            Location
                                        </label>
                                        <div className="relative">
                                            <MapPin className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-brand-ink/40" />
                                            <input
                                                id="location"
                                                type="text"
                                                name="location"
                                                defaultValue={filters.location}
                                                placeholder="City"
                                                className={inputClasses}
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="employment_type"
                                            className="mb-1.5 block text-sm font-bold text-brand-ink/70"
                                        >
                                            Employment Type
                                        </label>
                                        <select
                                            id="employment_type"
                                            name="employment_type"
                                            defaultValue={filters.employment_type}
                                            className={selectClasses}
                                        >
                                            <option value="">All types</option>
                                            {employmentTypes.map((option) => (
                                                <option key={option.value} value={option.value}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label
                                            htmlFor="work_mode"
                                            className="mb-1.5 block text-sm font-bold text-brand-ink/70"
                                        >
                                            Work Mode
                                        </label>
                                        <select
                                            id="work_mode"
                                            name="work_mode"
                                            defaultValue={filters.work_mode}
                                            className={selectClasses}
                                        >
                                            <option value="">All modes</option>
                                            {workModes.map((option) => (
                                                <option key={option.value} value={option.value}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="flex items-end sm:col-span-2 lg:col-span-5">
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-brand-gold to-brand-amber px-8 py-3 text-sm font-extrabold text-brand-ink shadow-lg shadow-brand-amber/40 disabled:opacity-60"
                                        >
                                            <SlidersHorizontal className="h-4 w-4" />
                                            {processing ? 'Searching…' : 'Search Roles'}
                                        </button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </Reveal>
                </div>
            </section>

            <section className="bg-white py-16">
                <div className="mx-auto max-w-7xl px-6 lg:px-8">
                    <p className="mb-8 text-sm font-semibold tracking-wide text-brand-ink/50 uppercase">
                        {jobs.total} {jobs.total === 1 ? 'role' : 'roles'} open
                    </p>

                    {jobs.data.length === 0 ? (
                        <Reveal className="rounded-3xl border border-dashed border-brand-ink/15 bg-brand-cream/40 p-16 text-center">
                            <Briefcase className="mx-auto h-10 w-10 text-brand-ink/30" />
                            <h3 className="mt-4 text-xl font-bold text-brand-ink">
                                No open roles match your search
                            </h3>
                            <p className="mt-2 text-brand-ink/60">
                                Try clearing filters or check back soon — new roles are
                                posted regularly.
                            </p>
                        </Reveal>
                    ) : (
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {jobs.data.map((job, i) => (
                                <Reveal
                                    key={job.slug}
                                    delay={(i % 3) * 80}
                                    className="group flex h-full flex-col rounded-2xl border border-brand-ink/10 bg-white p-6 shadow-sm shadow-black/5 transition-all hover:-translate-y-1 hover:shadow-lg hover:shadow-brand-gold/10"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-brand-gold/15 px-3 py-1 text-xs font-bold tracking-wide text-brand-ink uppercase">
                                            {job.employment_type_label}
                                        </span>
                                        <span className="text-xs font-medium text-brand-ink/50">
                                            {job.code}
                                        </span>
                                    </div>

                                    <h3 className="mt-4 text-xl font-bold text-brand-ink">
                                        {job.title}
                                    </h3>

                                    <div className="mt-2 flex items-center gap-1.5 text-sm font-medium text-brand-ink/60">
                                        <Building2 className="h-4 w-4 shrink-0" />
                                        {job.company_name ?? 'Pradhi Associates client'}
                                    </div>

                                    <div className="mt-1 flex items-center gap-1.5 text-sm text-brand-ink/60">
                                        <MapPin className="h-4 w-4 shrink-0" />
                                        {job.location} · {job.work_mode_label}
                                    </div>

                                    {job.salary_display && (
                                        <p className="mt-3 text-sm font-semibold text-brand-ink">
                                            {job.salary_display}
                                        </p>
                                    )}

                                    <div className="mt-6 border-t border-brand-ink/10 pt-4">
                                        <Link
                                            href={jobsShow.url(job.slug)}
                                            className="inline-flex items-center gap-1.5 text-sm font-bold text-brand-gold transition-transform group-hover:translate-x-1"
                                        >
                                            View Role & Apply
                                            <ArrowRight className="h-4 w-4" />
                                        </Link>
                                    </div>
                                </Reveal>
                            ))}
                        </div>
                    )}

                    {jobs.last_page > 1 && (
                        <div className="mt-12 flex flex-wrap items-center justify-center gap-2">
                            {jobs.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={`rounded-full px-4 py-2 text-sm font-bold ${
                                        link.active
                                            ? 'bg-brand-ink text-white'
                                            : link.url
                                              ? 'bg-brand-cream text-brand-ink hover:bg-brand-gold/20'
                                              : 'pointer-events-none text-brand-ink/30'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </section>
        </ThemeLayout>
    );
}
