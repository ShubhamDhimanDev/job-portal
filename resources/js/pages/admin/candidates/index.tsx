import { router, useForm } from '@inertiajs/react';
import { Download, Mail, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import {
    download,
    email,
} from '@/actions/App/Http/Controllers/Admin/CandidateExportController';
import {
    index,
    resume,
    update,
} from '@/actions/App/Http/Controllers/Admin/JobApplicationController';
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

interface JobPostingOption {
    id: number;
    title: string;
}

interface StatusOption {
    value: string;
    label: string;
}

interface CandidateRow {
    id: number;
    name: string;
    email: string;
    phone: string;
    status: string;
    status_label: string;
    admin_notes: string | null;
    applied_at: string | null;
    job_posting: {
        id: number;
        title: string;
    };
    company_name: string | null;
    resume_filename: string;
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
}

interface CandidatesIndexProps {
    candidates: PaginatedCandidates;
    jobPostings: JobPostingOption[];
    statuses: StatusOption[];
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
    const [emailDialogOpen, setEmailDialogOpen] = useState(false);

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
        router.get(
            index.url(),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function updateStatus(candidateId: number, newStatus: string) {
        router.patch(
            update.url(candidateId),
            { status: newStatus },
            { preserveState: true, preserveScroll: true },
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
                            className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6"
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

                            <div className="flex items-end gap-2 lg:col-span-6">
                                <Button type="submit">Apply filters</Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={resetFilters}
                                >
                                    Reset
                                </Button>

                                <div className="ml-auto flex gap-2">
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
                                                    <select
                                                        className="h-8 rounded-md border border-input bg-transparent px-2 text-xs shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                        value={candidate.status}
                                                        onChange={(e) =>
                                                            updateStatus(
                                                                candidate.id,
                                                                e.target.value,
                                                            )
                                                        }
                                                    >
                                                        {statuses.map(
                                                            (option) => (
                                                                <option
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
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </div>
                                            </td>
                                            <td className="py-3 pr-3 align-top whitespace-nowrap">
                                                {candidate.applied_at}
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                <a
                                                    href={resume.url(
                                                        candidate.id,
                                                    )}
                                                    className="text-primary underline-offset-4 hover:underline"
                                                >
                                                    {candidate.resume_filename}
                                                </a>
                                            </td>
                                        </tr>
                                    ))}

                                    {candidates.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={6}
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
        </AdminLayout>
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
