import { Form, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Briefcase,
    Building2,
    Calendar,
    CheckCircle2,
    GraduationCap,
    LoaderCircle,
    MapPin,
    Paperclip,
    Users,
} from 'lucide-react';
import {
    apply as applyToJob,
    index as jobsIndex,
} from '@/actions/App/Http/Controllers/JobBoardController';
import Reveal from '@/components/reveal';
import ThemeLayout from '@/layouts/theme-layout';
import { PHONE_ERROR, PHONE_PATTERN_SOURCE } from '@/lib/candidate-validation';

interface JobDetail {
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
    description: string;
    responsibilities: string | null;
    requirements: string | null;
    experience_level: string | null;
    vacancies: number;
}

interface JobShowProps {
    job: JobDetail;
}

function InfoRow({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof MapPin;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <div className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-gold/15">
                <Icon className="h-4 w-4 text-brand-ink" />
            </div>
            <div>
                <p className="text-xs font-bold tracking-wide text-brand-ink/50 uppercase">
                    {label}
                </p>
                <p className="text-sm font-semibold text-brand-ink">{value}</p>
            </div>
        </div>
    );
}

export default function JobShow({ job }: JobShowProps) {
    return (
        <ThemeLayout title={`${job.title} – Pradhi Associates`}>
            <section className="bg-brand-cream py-16">
                <div className="mx-auto max-w-7xl px-6 lg:px-8">
                    <Link
                        href={jobsIndex.url()}
                        className="inline-flex items-center gap-1.5 text-sm font-bold text-brand-ink/60 hover:text-brand-ink"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to all roles
                    </Link>

                    <Reveal delay={80} className="mt-6 max-w-3xl">
                        <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-brand-gold/20 px-3 py-1 text-xs font-bold tracking-wide text-brand-ink uppercase">
                            {job.employment_type_label}
                        </span>
                        <h1 className="mt-3 text-4xl font-extrabold text-brand-ink sm:text-5xl">
                            {job.title}
                        </h1>
                        <div className="mt-3 flex items-center gap-2 text-lg font-semibold text-brand-ink/70">
                            <Building2 className="h-5 w-5 shrink-0" />
                            {job.company_name ?? 'Pradhi Associates client'}
                        </div>
                    </Reveal>
                </div>
            </section>

            <section className="bg-white py-16">
                <div className="mx-auto grid max-w-7xl gap-12 px-6 lg:grid-cols-[1fr_400px] lg:px-8">
                    <Reveal className="min-w-0">
                        <div className="grid gap-6 rounded-3xl border border-brand-ink/10 bg-brand-cream/40 p-6 sm:grid-cols-2">
                            <InfoRow
                                icon={MapPin}
                                label="Location"
                                value={job.location}
                            />
                            <InfoRow
                                icon={Briefcase}
                                label="Work Mode"
                                value={job.work_mode_label}
                            />
                            {job.department && (
                                <InfoRow
                                    icon={Building2}
                                    label="Department"
                                    value={job.department}
                                />
                            )}
                            {job.experience_level && (
                                <InfoRow
                                    icon={GraduationCap}
                                    label="Experience"
                                    value={job.experience_level}
                                />
                            )}
                            <InfoRow
                                icon={Users}
                                label="Vacancies"
                                value={String(job.vacancies)}
                            />
                            {job.application_deadline && (
                                <InfoRow
                                    icon={Calendar}
                                    label="Apply By"
                                    value={job.application_deadline}
                                />
                            )}
                        </div>

                        {job.salary_display && (
                            <p className="mt-6 text-lg font-bold text-brand-ink">
                                {job.salary_display}
                            </p>
                        )}

                        <div className="mt-10 space-y-10">
                            <div>
                                <h2 className="text-2xl font-extrabold text-brand-ink">
                                    About the Role
                                </h2>
                                <p className="mt-3 leading-relaxed whitespace-pre-line text-brand-ink/70">
                                    {job.description}
                                </p>
                            </div>

                            {job.responsibilities && (
                                <div>
                                    <h2 className="text-2xl font-extrabold text-brand-ink">
                                        Responsibilities
                                    </h2>
                                    <p className="mt-3 leading-relaxed whitespace-pre-line text-brand-ink/70">
                                        {job.responsibilities}
                                    </p>
                                </div>
                            )}

                            {job.requirements && (
                                <div>
                                    <h2 className="text-2xl font-extrabold text-brand-ink">
                                        Requirements
                                    </h2>
                                    <p className="mt-3 leading-relaxed whitespace-pre-line text-brand-ink/70">
                                        {job.requirements}
                                    </p>
                                </div>
                            )}
                        </div>
                    </Reveal>

                    <Reveal delay={120}>
                        <div className="sticky top-28 rounded-3xl border border-brand-ink/10 bg-white p-6 shadow-xl shadow-black/5">
                            <h2 className="text-xl font-extrabold text-brand-ink">
                                Apply for this Role
                            </h2>
                            <p className="mt-1 text-sm text-brand-ink/60">
                                Fields marked with * are required.
                            </p>

                            <Form
                                {...applyToJob.form({ jobPosting: job.slug })}
                                resetOnSuccess
                                className="mt-6"
                            >
                                {({
                                    errors,
                                    processing,
                                    progress,
                                    wasSuccessful,
                                }) =>
                                    wasSuccessful ? (
                                        <div className="flex flex-col items-center gap-3 py-8 text-center">
                                            <CheckCircle2 className="h-12 w-12 text-brand-gold" />
                                            <p className="text-lg font-bold text-brand-ink">
                                                Application received!
                                            </p>
                                            <p className="text-sm text-brand-ink/60">
                                                Thanks for applying — the Pradhi
                                                Associates team will be in touch
                                                if you&apos;re shortlisted.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div>
                                                <label
                                                    htmlFor="name"
                                                    className="mb-1.5 block text-sm font-bold text-brand-ink/70"
                                                >
                                                    Full Name *
                                                </label>
                                                <input
                                                    id="name"
                                                    type="text"
                                                    name="name"
                                                    required
                                                    className="w-full rounded-xl border border-brand-ink/15 bg-white px-4 py-2.5 text-sm text-brand-ink focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                                                />
                                                {errors.name && (
                                                    <p className="mt-1.5 text-sm text-red-600">
                                                        {errors.name}
                                                    </p>
                                                )}
                                            </div>

                                            <div>
                                                <label
                                                    htmlFor="email"
                                                    className="mb-1.5 block text-sm font-bold text-brand-ink/70"
                                                >
                                                    Email *
                                                </label>
                                                <input
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    required
                                                    className="w-full rounded-xl border border-brand-ink/15 bg-white px-4 py-2.5 text-sm text-brand-ink focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                                                />
                                                {errors.email && (
                                                    <p className="mt-1.5 text-sm text-red-600">
                                                        {errors.email}
                                                    </p>
                                                )}
                                            </div>

                                            <div>
                                                <label
                                                    htmlFor="phone"
                                                    className="mb-1.5 block text-sm font-bold text-brand-ink/70"
                                                >
                                                    Phone *
                                                </label>
                                                <input
                                                    id="phone"
                                                    type="tel"
                                                    name="phone"
                                                    required
                                                    pattern={
                                                        PHONE_PATTERN_SOURCE
                                                    }
                                                    title={PHONE_ERROR}
                                                    className="w-full rounded-xl border border-brand-ink/15 bg-white px-4 py-2.5 text-sm text-brand-ink focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                                                />
                                                {errors.phone && (
                                                    <p className="mt-1.5 text-sm text-red-600">
                                                        {errors.phone}
                                                    </p>
                                                )}
                                            </div>

                                            <div>
                                                <label
                                                    htmlFor="resume"
                                                    className="mb-1.5 block text-sm font-bold text-brand-ink/70"
                                                >
                                                    Resume (PDF, DOC, DOCX — max
                                                    5MB) *
                                                </label>
                                                <div className="relative">
                                                    <Paperclip className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-brand-ink/40" />
                                                    <input
                                                        id="resume"
                                                        type="file"
                                                        name="resume"
                                                        required
                                                        accept=".pdf,.doc,.docx"
                                                        className="w-full rounded-xl border border-brand-ink/15 bg-white py-2.5 pr-4 pl-10 text-sm text-brand-ink file:mr-3 file:rounded-full file:border-0 file:bg-brand-gold/15 file:px-3 file:py-1 file:text-xs file:font-bold file:text-brand-ink focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                                                    />
                                                </div>
                                                {errors.resume && (
                                                    <p className="mt-1.5 text-sm text-red-600">
                                                        {errors.resume}
                                                    </p>
                                                )}
                                                {progress && (
                                                    <progress
                                                        value={
                                                            progress.percentage
                                                        }
                                                        max={100}
                                                        className="mt-2 h-1.5 w-full"
                                                    >
                                                        {progress.percentage}%
                                                    </progress>
                                                )}
                                            </div>

                                            <div>
                                                <label
                                                    htmlFor="cover_note"
                                                    className="mb-1.5 block text-sm font-bold text-brand-ink/70"
                                                >
                                                    Cover Note (optional)
                                                </label>
                                                <textarea
                                                    id="cover_note"
                                                    name="cover_note"
                                                    rows={4}
                                                    className="w-full rounded-xl border border-brand-ink/15 bg-white px-4 py-2.5 text-sm text-brand-ink focus:border-brand-gold focus:ring-1 focus:ring-brand-gold focus:outline-none"
                                                />
                                                {errors.cover_note && (
                                                    <p className="mt-1.5 text-sm text-red-600">
                                                        {errors.cover_note}
                                                    </p>
                                                )}
                                            </div>

                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-brand-gold to-brand-amber px-6 py-3 text-sm font-extrabold text-brand-ink shadow-lg shadow-brand-amber/40 disabled:opacity-60"
                                            >
                                                {processing && (
                                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                                )}
                                                Submit Application
                                            </button>
                                        </div>
                                    )
                                }
                            </Form>
                        </div>
                    </Reveal>
                </div>
            </section>
        </ThemeLayout>
    );
}
