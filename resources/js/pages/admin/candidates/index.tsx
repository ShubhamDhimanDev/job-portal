import { Link, router, useForm } from '@inertiajs/react';
import {
    Copy,
    Download,
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
import { create as importCandidates } from '@/actions/App/Http/Controllers/Admin/CandidateImportController';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';

interface JobPostingOption {
    id: number;
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
        title: string;
    };
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
    sort: string | null;
}

interface CandidatesIndexProps {
    candidates: PaginatedCandidates;
    jobPostings: JobPostingOption[];
    statuses: StatusOption[];
    genders: EnumOption[];
    interviewTypes: EnumOption[];
    duplicateCount: number;
    filters: CandidateFilters;
    flash: {
        success: string | null;
        error: string | null;
    };
}

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

export default function CandidatesIndex({
    candidates,
    jobPostings,
    statuses,
    genders,
    interviewTypes,
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
    const [sort, setSort] = useState(filters.sort ?? 'newest');
    const showingDuplicates = filters.duplicates === '1';
    const [emailDialogOpen, setEmailDialogOpen] = useState(false);
    const [reratingId, setReratingId] = useState<number | null>(null);
    const [aiDetailCandidate, setAiDetailCandidate] =
        useState<CandidateRow | null>(null);
    const [editCandidate, setEditCandidate] = useState<CandidateRow | null>(
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

        router.get(
            index.url(),
            {
                job_posting_id:
                    jobPostingId === 'all' ? undefined : jobPostingId,
                status: status === 'all' ? undefined : status,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                search: search || undefined,
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
        setSort('newest');
        router.get(
            index.url(),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggleDuplicates() {
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

        router.delete(destroy.url(candidate.id), { preserveScroll: true });
    }

    function deleteDuplicates() {
        if (
            !window.confirm(
                `Delete ${duplicateCount} duplicate candidate(s)? The oldest record in each group of matching email or phone is kept. This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(destroyDuplicates.url(), { preserveScroll: true });
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

    const exportUrl = download.url({
        query: {
            job_posting_id: filters.job_posting_id ?? undefined,
            status: filters.status ?? undefined,
            date_from: filters.date_from ?? undefined,
            date_to: filters.date_to ?? undefined,
            search: filters.search ?? undefined,
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
                                        {jobPostings.map((job) => (
                                            <SelectItem
                                                key={job.id}
                                                value={String(job.id)}
                                            >
                                                {job.title}
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
                                        placeholder="Name, email, phone"
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
                                    <Button variant="outline" asChild>
                                        <Link href={importCandidates.url()}>
                                            <Upload />
                                            Import
                                        </Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <a href={exportUrl}>
                                            <Download />
                                            Export
                                        </a>
                                    </Button>

                                    <EmailExportDialog
                                        open={emailDialogOpen}
                                        onOpenChange={setEmailDialogOpen}
                                        filters={filters}
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
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="pr-3 pb-2 font-medium">
                                            Candidate
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Job
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Company
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
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-3 align-top">
                                                <div className="font-medium">
                                                    {candidate.name}
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
                                            <td className="py-3 pr-3 align-top">
                                                {candidate.job_posting.title}
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                {candidate.company_name ?? '—'}
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
                                                            reratingId ===
                                                                candidate.id
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
                                                colSpan={7}
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

function CandidateProfileSummary({ candidate }: { candidate: CandidateRow }) {
    const details = [
        candidate.current_designation,
        candidate.current_company,
        candidate.current_location,
        candidate.total_experience !== null
            ? `${candidate.total_experience} yrs exp`
            : null,
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
    const { data, setData, patch, processing, errors, clearErrors } = useForm<
        CandidateProfileValues & {
            name: string;
            email: string;
            phone: string;
            job_posting_id: string;
        }
    >({
        ...emptyCandidateProfile,
        name: '',
        email: '',
        phone: '',
        job_posting_id: '',
    });

    useEffect(() => {
        if (candidate) {
            setData({
                name: candidate.name,
                email: candidate.email,
                phone: candidate.phone,
                job_posting_id: String(candidate.job_posting.id),
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
            });
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [candidate]);

    function submit(e: FormEvent) {
        e.preventDefault();

        if (!candidate) {
            return;
        }

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
                        Moving a candidate to another job re-runs their AI
                        rating.
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
                                {jobPostings.map((job) => (
                                    <SelectItem
                                        key={job.id}
                                        value={String(job.id)}
                                    >
                                        {job.title}
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
}

function EmailExportDialog({
    open,
    onOpenChange,
    filters,
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
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Email candidates export</DialogTitle>
                    <DialogDescription>
                        Sends the currently filtered candidates as an Excel
                        attachment.
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
                                AI fit rating for {candidate.job_posting.title}
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
