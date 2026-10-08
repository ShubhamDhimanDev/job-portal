import { Link, router, useForm } from '@inertiajs/react';
import {
    Copy,
    Download,
    Eye,
    Mail,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import {
    download,
    email,
} from '@/actions/App/Http/Controllers/Admin/CandidateExportController';
import {
    create,
    destroy,
    destroyDuplicates,
    index,
    rate,
    resume,
    update,
    uploadResume as uploadResumeAction,
} from '@/actions/App/Http/Controllers/Admin/JobApplicationController';
import {
    CandidateProfileFields,
    emptyCandidateProfile,
} from '@/components/candidate-profile-fields';
import type {
    CandidateProfileValues,
    EnumOption,
} from '@/components/candidate-profile-fields';
import {
    CandidateSkillsDialog,
    SkillsInput,
} from '@/components/candidate-skills';
import { ResumePreviewDialog } from '@/components/resume-preview-dialog';
import { SkillsFilter } from '@/components/skills-filter';
import type { SkillOption, SkillsMatch } from '@/components/skills-filter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { UploadResumesDialog } from '@/components/upload-resumes-dialog';
import AdminLayout from '@/layouts/admin-layout';
import { validateCandidateContact } from '@/lib/candidate-validation';
import { cn } from '@/lib/utils';

interface JobPostingOption {
    id: number;
    code: string;
    title: string;
}

interface StatusOption {
    value: string;
    label: string;
}

type AiStatus = 'pending' | 'processing' | 'completed' | 'failed';

interface AiProfileEducation {
    degree: string;
    institution: string;
    year: string | null;
}

interface AiProfileWorkHistory {
    company: string;
    title: string;
    start: string | null;
    end: string | null;
}

interface AiProfile {
    skills: string[];
    total_experience_years: number | null;
    education: AiProfileEducation[];
    work_history: AiProfileWorkHistory[];
    certifications: string[];
    summary: string;
}

interface CandidateRow {
    id: number;
    code: string;
    name: string;
    email: string;
    phone: string;
    gender: string | null;
    date_of_birth: string | null;
    total_experience: number | null;
    relevant_experience: number | null;
    current_company: string | null;
    industry_type: string | null;
    current_designation: string | null;
    current_location: string | null;
    current_ctc: number | null;
    expected_ctc: number | null;
    notice_period: string | null;
    interview_type: string | null;
    interview_type_label: string | null;
    status: string;
    status_label: string;
    admin_notes: string | null;
    applied_at: string | null;
    job_posting: {
        id: number;
        code: string;
        title: string;
    } | null;
    company_name: string | null;
    has_resume: boolean;
    resume_filename: string | null;
    ai_status: AiStatus;
    ai_status_label: string;
    ai_score: number | null;
    ai_reasoning: string | null;
    ai_strengths: string[] | null;
    ai_gaps: string[] | null;
    ai_profile: AiProfile | null;
    skills: string[];
    ai_error: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedCandidates {
    data: CandidateRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
}

interface CandidateFilters {
    job_posting_id: string | number | null;
    status: string | null;
    date_from: string | null;
    date_to: string | null;
    search: string | null;
    duplicates: string | null;
    experience_min: string | null;
    experience_max: string | null;
    salary_basis: string | null;
    salary_min: string | null;
    salary_max: string | null;
    notice_period: string | null;
    skills: string[];
    skills_match: string | null;
    sort: string | null;
}

interface CandidatesIndexProps {
    candidates: PaginatedCandidates;
    jobPostings: JobPostingOption[];
    statuses: StatusOption[];
    genders: EnumOption[];
    interviewTypes: EnumOption[];
    noticePeriods: EnumOption[];
    skillOptions: SkillOption[];
    duplicateCount: number;
    filters: CandidateFilters;
    flash: {
        success: string | null;
        error: string | null;
    };
}

const NO_JOB = 'none';

const statusBadgeVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    new: 'secondary',
    shortlisted: 'default',
    on_hold: 'outline',
    rejected: 'destructive',
    hired: 'default',
};

function aiScoreBadgeClassName(score: number): string {
    if (score >= 8) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400';
    }

    if (score >= 5) {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400';
    }

    return 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400';
}

function parseAddressList(value: string): string[] {
    return Array.from(
        new Set(
            value
                .split(/[\n,]/)
                .map((entry) => entry.trim())
                .filter((entry) => entry.length > 0),
        ),
    );
}

const MAX_EXPORT_SELECTION = 500;

export default function CandidatesIndex({
    candidates,
    jobPostings,
    statuses,
    genders,
    interviewTypes,
    noticePeriods,
    skillOptions,
    duplicateCount,
    filters,
    flash,
}: CandidatesIndexProps) {
    const [jobPostingId, setJobPostingId] = useState(
        filters.job_posting_id ? String(filters.job_posting_id) : 'all',
    );
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [search, setSearch] = useState(filters.search ?? '');
    const [experienceMin, setExperienceMin] = useState(
        filters.experience_min ?? '',
    );
    const [experienceMax, setExperienceMax] = useState(
        filters.experience_max ?? '',
    );
    const [salaryBasis, setSalaryBasis] = useState(
        filters.salary_basis ?? 'expected',
    );
    const [salaryMin, setSalaryMin] = useState(filters.salary_min ?? '');
    const [salaryMax, setSalaryMax] = useState(filters.salary_max ?? '');
    const [noticePeriod, setNoticePeriod] = useState(
        filters.notice_period ?? 'all',
    );
    const [sort, setSort] = useState(filters.sort ?? 'newest');
    const [skills, setSkills] = useState<string[]>(filters.skills);
    const [skillsMatch, setSkillsMatch] = useState<SkillsMatch>(
        filters.skills_match === 'any' ? 'any' : 'all',
    );
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const showingDuplicates = filters.duplicates === '1';
    const [emailDialogOpen, setEmailDialogOpen] = useState(false);
    const [reratingId, setReratingId] = useState<number | null>(null);
    const [aiDetailCandidate, setAiDetailCandidate] =
        useState<CandidateRow | null>(null);
    const [editCandidate, setEditCandidate] = useState<CandidateRow | null>(
        null,
    );
    const [previewCandidate, setPreviewCandidate] =
        useState<CandidateRow | null>(null);
    const [skillsCandidate, setSkillsCandidate] = useState<CandidateRow | null>(
        null,
    );

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.success, flash.error]);

    function applyFilters(e?: FormEvent) {
        e?.preventDefault();
        setSelectedIds([]);

        router.get(
            index.url(),
            {
                job_posting_id:
                    jobPostingId === 'all' ? undefined : jobPostingId,
                status: status === 'all' ? undefined : status,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                search: search || undefined,
                experience_min: experienceMin || undefined,
                experience_max: experienceMax || undefined,
                salary_basis:
                    salaryBasis === 'expected' ? undefined : salaryBasis,
                salary_min: salaryMin || undefined,
                salary_max: salaryMax || undefined,
                notice_period:
                    noticePeriod === 'all' ? undefined : noticePeriod,
                skills: skills.length > 0 ? skills : undefined,
                skills_match:
                    skills.length > 1 && skillsMatch === 'any'
                        ? 'any'
                        : undefined,
                duplicates: showingDuplicates ? 1 : undefined,
                sort: sort === 'newest' ? undefined : sort,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function resetFilters() {
        setJobPostingId('all');
        setStatus('all');
        setDateFrom('');
        setDateTo('');
        setSearch('');
        setExperienceMin('');
        setExperienceMax('');
        setSalaryBasis('expected');
        setSalaryMin('');
        setSalaryMax('');
        setNoticePeriod('all');
        setSkills([]);
        setSkillsMatch('all');
        setSort('newest');
        setSelectedIds([]);
        router.get(
            index.url(),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggleDuplicates() {
        setSelectedIds([]);
        router.get(index.url(), showingDuplicates ? {} : { duplicates: 1 }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function deleteCandidate(candidate: CandidateRow) {
        if (
            !window.confirm(`Delete ${candidate.name}? This cannot be undone.`)
        ) {
            return;
        }

        router.delete(destroy.url(candidate.id), {
            preserveScroll: true,
            onSuccess: () =>
                setSelectedIds((current) =>
                    current.filter((id) => id !== candidate.id),
                ),
        });
    }

    function deleteDuplicates() {
        if (
            !window.confirm(
                `Delete ${duplicateCount} duplicate candidate(s)? The oldest record in each group of matching email or phone is kept. This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(destroyDuplicates.url(), {
            preserveScroll: true,
            onSuccess: () => setSelectedIds([]),
        });
    }

    function updateStatus(candidateId: number, newStatus: string) {
        router.patch(
            update.url(candidateId),
            { status: newStatus },
            { preserveState: true, preserveScroll: true },
        );
    }

    function uploadResume(candidateId: number, file: File) {
        router.post(
            uploadResumeAction.url(candidateId),
            { resume: file },
            {
                forceFormData: true,
                preserveState: true,
                preserveScroll: true,
                onError: (errors) => {
                    toast.error(
                        errors.resume ?? 'Could not upload the resume.',
                    );
                },
            },
        );
    }

    function rerateCandidate(candidateId: number) {
        setReratingId(candidateId);
        router.post(
            rate.url(candidateId),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setReratingId(null),
            },
        );
    }

    const pageIds = candidates.data.map((candidate) => candidate.id);
    const selectedOnPage = pageIds.filter((id) =>
        selectedIds.includes(id),
    ).length;

    function selectCandidates(ids: number[]) {
        const next = Array.from(new Set([...selectedIds, ...ids]));

        if (next.length > MAX_EXPORT_SELECTION) {
            toast.error(
                `You can select up to ${MAX_EXPORT_SELECTION} candidates to export at a time.`,
            );

            return;
        }

        setSelectedIds(next);
    }

    function unselectCandidates(ids: number[]) {
        setSelectedIds(selectedIds.filter((id) => !ids.includes(id)));
    }

    // Ticked candidates are exported on their own; with none ticked,
    // everything matching the applied filters is exported.
    const exportUrl = download.url({
        query: {
            duplicates: filters.duplicates ?? undefined,
            skills: filters.skills.length > 0 ? filters.skills : undefined,
            skills_match: filters.skills_match ?? undefined,
            ids: selectedIds.length > 0 ? selectedIds.join(',') : undefined,
            job_posting_id: filters.job_posting_id ?? undefined,
            status: filters.status ?? undefined,
            date_from: filters.date_from ?? undefined,
            date_to: filters.date_to ?? undefined,
            search: filters.search ?? undefined,
            experience_min: filters.experience_min ?? undefined,
            experience_max: filters.experience_max ?? undefined,
            salary_basis: filters.salary_basis ?? undefined,
            salary_min: filters.salary_min ?? undefined,
            salary_max: filters.salary_max ?? undefined,
            notice_period: filters.notice_period ?? undefined,
        },
    });

    return (
        <AdminLayout title="Candidates">
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={applyFilters}
                            className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-7"
                        >
                            <div className="lg:col-span-2">
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Job
                                </Label>
                                <Select
                                    value={jobPostingId}
                                    onValueChange={setJobPostingId}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="All jobs" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All jobs
                                        </SelectItem>
                                        <SelectItem value="none">
                                            Unassigned
                                        </SelectItem>
                                        {jobPostings.map((job) => (
                                            <SelectItem
                                                key={job.id}
                                                value={String(job.id)}
                                            >
                                                {job.code} · {job.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Status
                                </Label>
                                <Select
                                    value={status}
                                    onValueChange={setStatus}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All statuses
                                        </SelectItem>
                                        {statuses.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    From
                                </Label>
                                <Input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) =>
                                        setDateFrom(e.target.value)
                                    }
                                />
                            </div>

                            <div>
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    To
                                </Label>
                                <Input
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                />
                            </div>

                            <div>
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Search
                                </Label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        className="pl-8"
                                        placeholder="Name, email, phone, code"
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                    />
                                </div>
                            </div>

                            <div>
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Sort
                                </Label>
                                <Select value={sort} onValueChange={setSort}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="newest">
                                            Newest
                                        </SelectItem>
                                        <SelectItem value="ai_score">
                                            Best fit
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Experience from (yrs)
                                </Label>
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.1"
                                    placeholder="e.g. 2"
                                    value={experienceMin}
                                    onChange={(e) =>
                                        setExperienceMin(e.target.value)
                                    }
                                />
                            </div>

                            <div>
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Experience to (yrs)
                                </Label>
                                <Input
                                    type="number"
                                    min={0}
                                    step="0.1"
                                    placeholder="e.g. 5"
                                    value={experienceMax}
                                    onChange={(e) =>
                                        setExperienceMax(e.target.value)
                                    }
                                />
                            </div>

                            <div>
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Salary basis
                                </Label>
                                <Select
                                    value={salaryBasis}
                                    onValueChange={setSalaryBasis}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="expected">
                                            Expected CTC
                                        </SelectItem>
                                        <SelectItem value="current">
                                            Current CTC
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Salary min
                                </Label>
                                <Input
                                    type="number"
                                    min={0}
                                    step="any"
                                    value={salaryMin}
                                    onChange={(e) =>
                                        setSalaryMin(e.target.value)
                                    }
                                />
                            </div>

                            <div>
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Salary max
                                </Label>
                                <Input
                                    type="number"
                                    min={0}
                                    step="any"
                                    value={salaryMax}
                                    onChange={(e) =>
                                        setSalaryMax(e.target.value)
                                    }
                                />
                            </div>

                            <div className="lg:col-span-2">
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Notice period
                                </Label>
                                <Select
                                    value={noticePeriod}
                                    onValueChange={setNoticePeriod}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Any notice period" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Any notice period
                                        </SelectItem>
                                        {noticePeriods.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="lg:col-span-5">
                                <Label className="mb-1.5 block text-xs text-muted-foreground">
                                    Skills
                                </Label>
                                <SkillsFilter
                                    options={skillOptions}
                                    selected={skills}
                                    match={skillsMatch}
                                    onChange={setSkills}
                                    onMatchChange={setSkillsMatch}
                                />
                            </div>

                            <div className="flex flex-wrap items-end gap-2 lg:col-span-7">
                                <Button type="submit">Apply filters</Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={resetFilters}
                                >
                                    Reset
                                </Button>

                                <div className="ml-auto flex flex-wrap gap-2">
                                    <Button asChild>
                                        <Link href={create.url()}>
                                            <Plus />
                                            Add candidate
                                        </Link>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={
                                            showingDuplicates
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                        onClick={toggleDuplicates}
                                    >
                                        <Copy />
                                        {showingDuplicates
                                            ? 'Show all'
                                            : 'Find duplicates'}
                                    </Button>
                                    {duplicateCount > 0 && (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            onClick={deleteDuplicates}
                                        >
                                            <Trash2 />
                                            Delete duplicates ({duplicateCount})
                                        </Button>
                                    )}
                                    <UploadResumesDialog
                                        jobPostings={jobPostings}
                                    />
                                    <Button variant="outline" asChild>
                                        <a href={exportUrl}>
                                            <Download />
                                            Export
                                            {selectedIds.length > 0 &&
                                                ` (${selectedIds.length})`}
                                        </a>
                                    </Button>

                                    <EmailExportDialog
                                        open={emailDialogOpen}
                                        onOpenChange={setEmailDialogOpen}
                                        filters={filters}
                                        selectedIds={selectedIds}
                                        total={candidates.total}
                                    />
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Candidates{' '}
                            <span className="font-normal text-muted-foreground">
                                ({candidates.total})
                            </span>
                        </CardTitle>
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            {selectedIds.length > 0 ? (
                                <>
                                    <span className="font-medium text-foreground">
                                        {selectedIds.length} selected
                                    </span>
                                    <span>
                                        Export and Email export include only
                                        these.
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedIds([])}
                                        className="underline-offset-2 hover:underline"
                                    >
                                        Clear selection
                                    </button>
                                </>
                            ) : (
                                <span>
                                    Export and Email export include all{' '}
                                    {candidates.total} candidates matching the
                                    filters. Tick rows to export only some.
                                </span>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="w-8 pr-3 pb-2">
                                            <Checkbox
                                                aria-label="Select all candidates on this page"
                                                disabled={pageIds.length === 0}
                                                checked={
                                                    pageIds.length > 0 &&
                                                    selectedOnPage ===
                                                        pageIds.length
                                                        ? true
                                                        : selectedOnPage > 0
                                                          ? 'indeterminate'
                                                          : false
                                                }
                                                onCheckedChange={(checked) =>
                                                    checked === true
                                                        ? selectCandidates(
                                                              pageIds,
                                                          )
                                                        : unselectCandidates(
                                                              pageIds,
                                                          )
                                                }
                                            />
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Candidate
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Experience
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Current company
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Location
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Skills
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Status
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            AI Fit
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Applied
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Resume
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {candidates.data.map((candidate) => (
                                        <tr
                                            key={candidate.id}
                                            className={cn(
                                                'border-b last:border-0',
                                                selectedIds.includes(
                                                    candidate.id,
                                                ) && 'bg-muted/40',
                                            )}
                                        >
                                            <td className="py-3 pr-3 align-top">
                                                <Checkbox
                                                    aria-label={`Select ${candidate.name}`}
                                                    checked={selectedIds.includes(
                                                        candidate.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        checked === true
                                                            ? selectCandidates([
                                                                  candidate.id,
                                                              ])
                                                            : unselectCandidates(
                                                                  [
                                                                      candidate.id,
                                                                  ],
                                                              )
                                                    }
                                                />
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                <div className="font-medium">
                                                    {candidate.name}
                                                </div>
                                                <div className="mt-0.5 flex flex-wrap items-center gap-1.5">
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {candidate.code}
                                                    </span>
                                                    {candidate.job_posting ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="font-mono text-[10px] font-normal"
                                                            title={[
                                                                candidate
                                                                    .job_posting
                                                                    .title,
                                                                candidate.company_name,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        >
                                                            {
                                                                candidate
                                                                    .job_posting
                                                                    .code
                                                            }
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-[10px] font-normal text-muted-foreground"
                                                        >
                                                            Unassigned
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {candidate.email}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {candidate.phone}
                                                </div>
                                                <CandidateProfileSummary
                                                    candidate={candidate}
                                                />
                                                <div className="mt-1 flex gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-6 px-1.5 text-xs text-muted-foreground"
                                                        onClick={() =>
                                                            setEditCandidate(
                                                                candidate,
                                                            )
                                                        }
                                                    >
                                                        <Pencil className="size-3" />
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-6 px-1.5 text-xs text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            deleteCandidate(
                                                                candidate,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-3" />
                                                        Delete
                                                    </Button>
                                                </div>
                                            </td>
                                            <td className="py-3 pr-3 align-top whitespace-nowrap">
                                                <CandidateExperience
                                                    candidate={candidate}
                                                />
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                {candidate.current_company ||
                                                candidate.current_designation ? (
                                                    <>
                                                        <div className="font-medium">
                                                            {candidate.current_company ??
                                                                '—'}
                                                        </div>
                                                        {candidate.current_designation && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    candidate.current_designation
                                                                }
                                                            </div>
                                                        )}
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                {candidate.current_location ?? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                <CandidateSkills
                                                    candidate={candidate}
                                                    onOpen={() =>
                                                        setSkillsCandidate(
                                                            candidate,
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                <div className="flex flex-col gap-1.5">
                                                    <Badge
                                                        variant={
                                                            statusBadgeVariant[
                                                                candidate.status
                                                            ] ?? 'secondary'
                                                        }
                                                        className="w-fit"
                                                    >
                                                        {candidate.status_label}
                                                    </Badge>
                                                    <Select
                                                        value={candidate.status}
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updateStatus(
                                                                candidate.id,
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger className="h-8 w-fit text-xs">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {statuses.map(
                                                                (option) => (
                                                                    <SelectItem
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        value={
                                                                            option.value
                                                                        }
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                <div className="flex flex-col items-start gap-1.5">
                                                    {candidate.ai_status ===
                                                        'completed' &&
                                                    candidate.ai_score !==
                                                        null ? (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setAiDetailCandidate(
                                                                    candidate,
                                                                )
                                                            }
                                                            className="cursor-pointer"
                                                        >
                                                            <Badge
                                                                variant="outline"
                                                                className={cn(
                                                                    'w-fit',
                                                                    aiScoreBadgeClassName(
                                                                        candidate.ai_score,
                                                                    ),
                                                                )}
                                                            >
                                                                {
                                                                    candidate.ai_score
                                                                }
                                                                /10
                                                            </Badge>
                                                        </button>
                                                    ) : candidate.ai_status ===
                                                      'failed' ? (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setAiDetailCandidate(
                                                                    candidate,
                                                                )
                                                            }
                                                            className="cursor-pointer"
                                                        >
                                                            <Badge
                                                                variant="destructive"
                                                                className="w-fit"
                                                            >
                                                                {
                                                                    candidate.ai_status_label
                                                                }
                                                            </Badge>
                                                        </button>
                                                    ) : candidate.job_posting ===
                                                      null ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="w-fit text-muted-foreground"
                                                        >
                                                            Not rated
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="w-fit text-muted-foreground"
                                                        >
                                                            {
                                                                candidate.ai_status_label
                                                            }
                                                        </Badge>
                                                    )}

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-6 px-1.5 text-xs text-muted-foreground"
                                                        disabled={
                                                            !candidate.has_resume ||
                                                            candidate.job_posting ===
                                                                null ||
                                                            reratingId ===
                                                                candidate.id
                                                        }
                                                        title={
                                                            candidate.job_posting ===
                                                            null
                                                                ? 'Assign a job to rate this candidate'
                                                                : undefined
                                                        }
                                                        onClick={() =>
                                                            rerateCandidate(
                                                                candidate.id,
                                                            )
                                                        }
                                                    >
                                                        <RefreshCw
                                                            className={cn(
                                                                'size-3',
                                                                reratingId ===
                                                                    candidate.id &&
                                                                    'animate-spin',
                                                            )}
                                                        />
                                                        {candidate.ai_status ===
                                                        'failed'
                                                            ? 'Retry'
                                                            : 'Re-rate'}
                                                    </Button>
                                                </div>
                                            </td>
                                            <td className="py-3 pr-3 align-top whitespace-nowrap">
                                                {candidate.applied_at}
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                <div className="flex flex-col items-start gap-1.5">
                                                    {candidate.has_resume ? (
                                                        <div className="flex flex-wrap gap-1.5">
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setPreviewCandidate(
                                                                        candidate,
                                                                    )
                                                                }
                                                            >
                                                                <Eye />
                                                                View
                                                            </Button>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                asChild
                                                            >
                                                                <a
                                                                    href={resume.url(
                                                                        candidate.id,
                                                                    )}
                                                                    download
                                                                >
                                                                    <Download />
                                                                    Download
                                                                </a>
                                                            </Button>
                                                        </div>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="w-fit text-muted-foreground"
                                                        >
                                                            No resume
                                                        </Badge>
                                                    )}
                                                    <ResumeUploadButton
                                                        label={
                                                            candidate.has_resume
                                                                ? 'Replace'
                                                                : 'Upload resume'
                                                        }
                                                        onSelect={(file) =>
                                                            uploadResume(
                                                                candidate.id,
                                                                file,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}

                                    {candidates.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={10}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No candidates match these
                                                filters.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {candidates.links.length > 3 && (
                            <div className="mt-4 flex flex-wrap items-center gap-1">
                                {candidates.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() =>
                                            link.url &&
                                            router.get(
                                                link.url,
                                                {},
                                                {
                                                    preserveState: true,
                                                    preserveScroll: true,
                                                },
                                            )
                                        }
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <EditCandidateDialog
                candidate={editCandidate}
                jobPostings={jobPostings}
                genders={genders}
                interviewTypes={interviewTypes}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditCandidate(null);
                    }
                }}
            />

            <ResumePreviewDialog
                candidate={previewCandidate}
                onOpenChange={(open) => {
                    if (!open) {
                        setPreviewCandidate(null);
                    }
                }}
            />

            <CandidateSkillsDialog
                candidate={
                    skillsCandidate
                        ? {
                              name: skillsCandidate.name,
                              code: skillsCandidate.code,
                              skills: skillsCandidate.skills,
                          }
                        : null
                }
                onOpenChange={(open) => {
                    if (!open) {
                        setSkillsCandidate(null);
                    }
                }}
            />

            <CandidateAiDetailDialog
                candidate={aiDetailCandidate}
                onOpenChange={(open) => {
                    if (!open) {
                        setAiDetailCandidate(null);
                    }
                }}
            />
        </AdminLayout>
    );
}

function CandidateExperience({ candidate }: { candidate: CandidateRow }) {
    const estimatedYears = candidate.ai_profile?.total_experience_years ?? null;
    const totalYears = candidate.total_experience ?? estimatedYears;

    if (totalYears === null && candidate.relevant_experience === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="space-y-0.5">
            {totalYears !== null && (
                <div className="font-medium">
                    {totalYears} yrs
                    {candidate.total_experience === null && (
                        <span className="ml-1 text-xs font-normal text-muted-foreground">
                            (AI est.)
                        </span>
                    )}
                </div>
            )}
            {candidate.relevant_experience !== null && (
                <div className="text-xs text-muted-foreground">
                    {candidate.relevant_experience} yrs relevant
                </div>
            )}
        </div>
    );
}

const MAX_SKILLS_SHOWN = 6;

interface CandidateSkillsProps {
    candidate: CandidateRow;
    onOpen: () => void;
}

function CandidateSkills({ candidate, onOpen }: CandidateSkillsProps) {
    const skills = candidate.skills;

    if (skills.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <button
            type="button"
            onClick={onOpen}
            title="View all skills"
            className="flex max-w-56 cursor-pointer flex-wrap gap-1 text-left"
        >
            {skills.slice(0, MAX_SKILLS_SHOWN).map((skill, index) => (
                <Badge
                    key={`${skill}-${index}`}
                    variant="secondary"
                    className="font-normal"
                >
                    {skill}
                </Badge>
            ))}
            {skills.length > MAX_SKILLS_SHOWN && (
                <Badge variant="outline" className="font-normal">
                    +{skills.length - MAX_SKILLS_SHOWN} more
                </Badge>
            )}
        </button>
    );
}

function CandidateProfileSummary({ candidate }: { candidate: CandidateRow }) {
    const details = [
        candidate.expected_ctc !== null
            ? `Expected CTC ${candidate.expected_ctc}`
            : null,
        candidate.notice_period ? `Notice ${candidate.notice_period}` : null,
        candidate.interview_type_label,
    ].filter((detail): detail is string => Boolean(detail));

    if (details.length === 0) {
        return null;
    }

    return (
        <div className="mt-1 max-w-64 text-xs text-muted-foreground">
            {details.join(' · ')}
        </div>
    );
}

interface ResumeUploadButtonProps {
    label: string;
    onSelect: (file: File) => void;
}

function ResumeUploadButton({ label, onSelect }: ResumeUploadButtonProps) {
    const inputRef = useRef<HTMLInputElement>(null);

    return (
        <>
            <input
                ref={inputRef}
                type="file"
                accept=".pdf,.doc,.docx"
                className="hidden"
                onChange={(e) => {
                    const file = e.target.files?.[0];

                    if (file) {
                        onSelect(file);
                    }

                    e.target.value = '';
                }}
            />
            <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-6 px-1.5 text-xs text-muted-foreground"
                onClick={() => inputRef.current?.click()}
            >
                <Upload className="size-3" />
                {label}
            </Button>
        </>
    );
}

interface EditCandidateDialogProps {
    candidate: CandidateRow | null;
    jobPostings: JobPostingOption[];
    genders: EnumOption[];
    interviewTypes: EnumOption[];
    onOpenChange: (open: boolean) => void;
}

function EditCandidateDialog({
    candidate,
    jobPostings,
    genders,
    interviewTypes,
    onOpenChange,
}: EditCandidateDialogProps) {
    const {
        data,
        setData,
        patch,
        processing,
        errors,
        clearErrors,
        setError,
        transform,
    } = useForm<
        CandidateProfileValues & {
            name: string;
            email: string;
            phone: string;
            job_posting_id: string;
            skills: string[];
            admin_notes: string;
        }
    >({
        ...emptyCandidateProfile,
        name: '',
        email: '',
        phone: '',
        job_posting_id: NO_JOB,
        skills: [],
        admin_notes: '',
    });

    useEffect(() => {
        if (candidate) {
            setData({
                name: candidate.name,
                email: candidate.email,
                phone: candidate.phone,
                job_posting_id: candidate.job_posting
                    ? String(candidate.job_posting.id)
                    : NO_JOB,
                gender: candidate.gender ?? '',
                date_of_birth: candidate.date_of_birth ?? '',
                total_experience: candidate.total_experience?.toString() ?? '',
                relevant_experience:
                    candidate.relevant_experience?.toString() ?? '',
                current_company: candidate.current_company ?? '',
                industry_type: candidate.industry_type ?? '',
                current_designation: candidate.current_designation ?? '',
                current_location: candidate.current_location ?? '',
                current_ctc: candidate.current_ctc?.toString() ?? '',
                expected_ctc: candidate.expected_ctc?.toString() ?? '',
                notice_period: candidate.notice_period ?? '',
                interview_type: candidate.interview_type ?? '',
                skills: candidate.skills,
                admin_notes: candidate.admin_notes ?? '',
            });
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [candidate]);

    const skillsError =
        errors.skills ??
        Object.entries(errors).find(([key]) => key.startsWith('skills.'))?.[1];

    function submit(e: FormEvent) {
        e.preventDefault();

        if (!candidate) {
            return;
        }

        clearErrors('email', 'phone');

        const contactErrors = validateCandidateContact(data);

        if (Object.keys(contactErrors).length > 0) {
            setError(contactErrors);

            return;
        }

        transform((formData) => ({
            ...formData,
            job_posting_id:
                formData.job_posting_id === NO_JOB
                    ? null
                    : formData.job_posting_id,
        }));

        patch(update.url(candidate.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={candidate !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit candidate</DialogTitle>
                    <DialogDescription>
                        Assigning or moving a candidate to a job re-runs their
                        AI rating.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="edit-name">Name</Label>
                        <Input
                            id="edit-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="edit-email">Email</Label>
                            <Input
                                id="edit-email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                            {errors.email && (
                                <p className="text-sm text-destructive">
                                    {errors.email}
                                </p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="edit-phone">Phone</Label>
                            <Input
                                id="edit-phone"
                                type="tel"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                            />
                            {errors.phone && (
                                <p className="text-sm text-destructive">
                                    {errors.phone}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Job</Label>
                        <Select
                            value={data.job_posting_id}
                            onValueChange={(value) =>
                                setData('job_posting_id', value)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_JOB}>
                                    Unassigned
                                </SelectItem>
                                {jobPostings.map((job) => (
                                    <SelectItem
                                        key={job.id}
                                        value={String(job.id)}
                                    >
                                        {job.code} · {job.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.job_posting_id && (
                            <p className="text-sm text-destructive">
                                {errors.job_posting_id}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <CandidateProfileFields
                            idPrefix="edit"
                            values={data}
                            errors={errors}
                            genders={genders}
                            interviewTypes={interviewTypes}
                            onChange={(field, value) => setData(field, value)}
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="edit-skills">Skills</Label>
                        <SkillsInput
                            id="edit-skills"
                            skills={data.skills}
                            onChange={(skills) => setData('skills', skills)}
                        />
                        {skillsError && (
                            <p className="text-sm text-destructive">
                                {skillsError}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Filled in from the AI rating of the resume. Your
                            changes are kept until the resume is replaced.
                        </p>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="edit-comment">Comment</Label>
                        <textarea
                            id="edit-comment"
                            rows={4}
                            maxLength={5000}
                            className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm text-foreground shadow-xs outline-none placeholder:text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="Notes from a call, availability, anything worth remembering"
                            value={data.admin_notes}
                            onChange={(e) =>
                                setData('admin_notes', e.target.value)
                            }
                        />
                        {errors.admin_notes && (
                            <p className="text-sm text-destructive">
                                {errors.admin_notes}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Internal note. The AI reads it when rating this
                            candidate, so changing it re-runs the rating.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

interface EmailExportDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    filters: CandidateFilters;
    selectedIds: number[];
    total: number;
}

function EmailExportDialog({
    open,
    onOpenChange,
    filters,
    selectedIds,
    total,
}: EmailExportDialogProps) {
    const { data, setData, post, processing, errors, reset, transform } =
        useForm({
            to: '',
            cc: '',
            bcc: '',
            subject: `Candidates export - ${new Date().toLocaleDateString()}`,
            message:
                'Hi,\n\nPlease find the attached candidates export.\n\nThanks.',
        });

    function submit(e: FormEvent) {
        e.preventDefault();

        transform((formData) => ({
            ...formData,
            to: parseAddressList(formData.to),
            cc: parseAddressList(formData.cc),
            bcc: parseAddressList(formData.bcc),
            job_posting_id: filters.job_posting_id ?? undefined,
            status: filters.status ?? undefined,
            date_from: filters.date_from ?? undefined,
            date_to: filters.date_to ?? undefined,
            search: filters.search ?? undefined,
            experience_min: filters.experience_min ?? undefined,
            experience_max: filters.experience_max ?? undefined,
            salary_basis: filters.salary_basis ?? undefined,
            salary_min: filters.salary_min ?? undefined,
            salary_max: filters.salary_max ?? undefined,
            notice_period: filters.notice_period ?? undefined,
            duplicates: filters.duplicates ?? undefined,
            skills: filters.skills.length > 0 ? filters.skills : undefined,
            skills_match: filters.skills_match ?? undefined,
            ids: selectedIds.length > 0 ? selectedIds : undefined,
        }));

        post(email.url(), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Candidates export emailed successfully.');
                onOpenChange(false);
                reset();
            },
            onError: () => {
                toast.error(
                    'Could not send the export. Check the fields below.',
                );
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>
                <Button>
                    <Mail />
                    Email export
                    {selectedIds.length > 0 && ` (${selectedIds.length})`}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Email candidates export</DialogTitle>
                    <DialogDescription>
                        {selectedIds.length > 0
                            ? `Sends the ${selectedIds.length} selected candidate${selectedIds.length === 1 ? '' : 's'} as an Excel attachment.`
                            : `Sends all ${total} candidates matching the current filters as an Excel attachment.`}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="to">To</Label>
                        <textarea
                            id="to"
                            className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="One email per line or comma-separated"
                            value={data.to}
                            onChange={(e) => setData('to', e.target.value)}
                        />
                        {errors.to && (
                            <p className="text-sm text-destructive">
                                {errors.to}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="cc">Cc</Label>
                            <textarea
                                id="cc"
                                className="flex min-h-12 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={data.cc}
                                onChange={(e) => setData('cc', e.target.value)}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="bcc">Bcc</Label>
                            <textarea
                                id="bcc"
                                className="flex min-h-12 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={data.bcc}
                                onChange={(e) => setData('bcc', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="subject">Subject</Label>
                        <Input
                            id="subject"
                            value={data.subject}
                            onChange={(e) => setData('subject', e.target.value)}
                        />
                        {errors.subject && (
                            <p className="text-sm text-destructive">
                                {errors.subject}
                            </p>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="message">Message</Label>
                        <textarea
                            id="message"
                            className="flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                        />
                        {errors.message && (
                            <p className="text-sm text-destructive">
                                {errors.message}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Sending…' : 'Send export'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

interface CandidateAiDetailDialogProps {
    candidate: CandidateRow | null;
    onOpenChange: (open: boolean) => void;
}

function CandidateAiDetailDialog({
    candidate,
    onOpenChange,
}: CandidateAiDetailDialogProps) {
    const profile = candidate?.ai_profile ?? null;

    return (
        <Dialog open={candidate !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                {candidate && (
                    <>
                        <DialogHeader>
                            <DialogTitle className="flex flex-wrap items-center gap-2">
                                {candidate.name}
                                {candidate.ai_score !== null && (
                                    <Badge
                                        variant="outline"
                                        className={aiScoreBadgeClassName(
                                            candidate.ai_score,
                                        )}
                                    >
                                        {candidate.ai_score}/10
                                    </Badge>
                                )}
                            </DialogTitle>
                            <DialogDescription>
                                {candidate.code} · AI fit rating for{' '}
                                {candidate.job_posting
                                    ? `${candidate.job_posting.code} ${candidate.job_posting.title}`
                                    : 'no job'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-5">
                            {candidate.ai_status === 'failed' &&
                                candidate.ai_error && (
                                    <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                                        {candidate.ai_error}
                                    </div>
                                )}

                            {candidate.ai_reasoning && (
                                <div>
                                    <h4 className="mb-1 text-sm font-medium">
                                        Reasoning
                                    </h4>
                                    <p className="text-sm text-muted-foreground">
                                        {candidate.ai_reasoning}
                                    </p>
                                </div>
                            )}

                            {candidate.ai_strengths &&
                                candidate.ai_strengths.length > 0 && (
                                    <div>
                                        <h4 className="mb-1 text-sm font-medium">
                                            Strengths
                                        </h4>
                                        <ul className="list-inside list-disc text-sm text-muted-foreground">
                                            {candidate.ai_strengths.map(
                                                (strength) => (
                                                    <li key={strength}>
                                                        {strength}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}

                            {candidate.ai_gaps &&
                                candidate.ai_gaps.length > 0 && (
                                    <div>
                                        <h4 className="mb-1 text-sm font-medium">
                                            Gaps
                                        </h4>
                                        <ul className="list-inside list-disc text-sm text-muted-foreground">
                                            {candidate.ai_gaps.map((gap) => (
                                                <li key={gap}>{gap}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                            {profile && (
                                <div className="space-y-4 border-t pt-4">
                                    <h3 className="text-sm font-semibold">
                                        Parsed resume profile
                                    </h3>

                                    {profile.summary && (
                                        <p className="text-sm text-muted-foreground">
                                            {profile.summary}
                                        </p>
                                    )}

                                    <div>
                                        <h4 className="mb-1 text-xs text-muted-foreground">
                                            Total experience
                                        </h4>
                                        <p className="text-sm">
                                            {profile.total_experience_years !==
                                            null
                                                ? `${profile.total_experience_years} years`
                                                : '—'}
                                        </p>
                                    </div>

                                    {profile.skills.length > 0 && (
                                        <div>
                                            <h4 className="mb-1.5 text-sm font-medium">
                                                Skills
                                            </h4>
                                            <div className="flex flex-wrap gap-1.5">
                                                {profile.skills.map((skill) => (
                                                    <Badge
                                                        key={skill}
                                                        variant="secondary"
                                                    >
                                                        {skill}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {profile.work_history.length > 0 && (
                                        <div>
                                            <h4 className="mb-1.5 text-sm font-medium">
                                                Work history
                                            </h4>
                                            <ul className="space-y-2 text-sm">
                                                {profile.work_history.map(
                                                    (job, index) => (
                                                        <li
                                                            key={`${job.company}-${job.title}-${index}`}
                                                        >
                                                            <div className="font-medium">
                                                                {job.title} ·{' '}
                                                                {job.company}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {job.start ??
                                                                    '—'}{' '}
                                                                –{' '}
                                                                {job.end ??
                                                                    'Present'}
                                                            </div>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    )}

                                    {profile.education.length > 0 && (
                                        <div>
                                            <h4 className="mb-1.5 text-sm font-medium">
                                                Education
                                            </h4>
                                            <ul className="space-y-2 text-sm">
                                                {profile.education.map(
                                                    (edu, index) => (
                                                        <li
                                                            key={`${edu.institution}-${edu.degree}-${index}`}
                                                        >
                                                            <div className="font-medium">
                                                                {edu.degree}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    edu.institution
                                                                }
                                                                {edu.year
                                                                    ? ` · ${edu.year}`
                                                                    : ''}
                                                            </div>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    )}

                                    {profile.certifications.length > 0 && (
                                        <div>
                                            <h4 className="mb-1.5 text-sm font-medium">
                                                Certifications
                                            </h4>
                                            <div className="flex flex-wrap gap-1.5">
                                                {profile.certifications.map(
                                                    (cert) => (
                                                        <Badge
                                                            key={cert}
                                                            variant="outline"
                                                        >
                                                            {cert}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                Close
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
