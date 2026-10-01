import { Link, router, useForm } from '@inertiajs/react';
import { Download, LoaderCircle } from 'lucide-react';
import { useEffect } from 'react';
import type { FormEventHandler } from 'react';
import { toast } from 'sonner';
import {
    issues,
    store,
    template,
} from '@/actions/App/Http/Controllers/Admin/CandidateImportController';
import { index } from '@/actions/App/Http/Controllers/Admin/JobApplicationController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';

interface ImportRow {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    status_label: string;
    total: number;
    created_count: number;
    skipped_count: number;
    failed_count: number;
    warning_count: number;
    has_issues: boolean;
    error: string | null;
    created_at: string | null;
}

interface JobReference {
    id: number;
    title: string;
    slug: string;
}

interface ImportCandidatesProps {
    imports: ImportRow[];
    jobPostings: JobReference[];
    flash: {
        success: string | null;
        error: string | null;
    };
}

const statusVariant: Record<
    ImportRow['status'],
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    processing: 'secondary',
    completed: 'default',
    failed: 'destructive',
};

export default function ImportCandidates({
    imports,
    jobPostings,
    flash,
}: ImportCandidatesProps) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        spreadsheet: File | null;
        archive: File | null;
        rate_with_ai: boolean;
    }>({
        spreadsheet: null,
        archive: null,
        rate_with_ai: true,
    });

    const isRunning = imports.some(
        (item) => item.status === 'pending' || item.status === 'processing',
    );

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.success, flash.error]);

    useEffect(() => {
        if (!isRunning) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['imports'] });
        }, 3000);

        return () => window.clearInterval(timer);
    }, [isRunning]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), {
            forceFormData: true,
            onSuccess: () => reset('spreadsheet', 'archive'),
        });
    };

    return (
        <AdminLayout title="Import Candidates">
            <div className="mx-auto flex max-w-3xl flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Bulk import</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-5">
                            <div className="rounded-md border bg-muted/40 p-4 text-sm text-muted-foreground">
                                <p>
                                    Upload a CSV or XLSX with the columns{' '}
                                    <span className="font-medium text-foreground">
                                        name, email, phone, job, resume_filename
                                    </span>
                                    . <span className="font-medium">job</span>{' '}
                                    is the job's slug or ID, and{' '}
                                    <span className="font-medium">
                                        resume_filename
                                    </span>{' '}
                                    should match a file inside the ZIP. It can
                                    be left blank: the candidate is still added,
                                    and a resume can be uploaded later from the
                                    candidates list.
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3"
                                    asChild
                                >
                                    <a href={template.url()}>
                                        <Download />
                                        Download Excel template (job dropdown)
                                    </a>
                                </Button>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="spreadsheet">
                                    Candidate list (CSV or XLSX)
                                </Label>
                                <Input
                                    id="spreadsheet"
                                    type="file"
                                    accept=".csv,.xlsx"
                                    onChange={(e) =>
                                        setData(
                                            'spreadsheet',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                    aria-invalid={Boolean(errors.spreadsheet)}
                                />
                                {errors.spreadsheet && (
                                    <p className="text-sm text-destructive">
                                        {errors.spreadsheet}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="archive">
                                    Resumes (ZIP, up to 100MB)
                                </Label>
                                <Input
                                    id="archive"
                                    type="file"
                                    accept=".zip"
                                    onChange={(e) =>
                                        setData(
                                            'archive',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                    aria-invalid={Boolean(errors.archive)}
                                />
                                {errors.archive && (
                                    <p className="text-sm text-destructive">
                                        {errors.archive}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="rate_with_ai"
                                    checked={data.rate_with_ai}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'rate_with_ai',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="rate_with_ai">
                                    Rate imported candidates with AI
                                </Label>
                            </div>

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing || !data.spreadsheet}
                                >
                                    {processing && (
                                        <LoaderCircle className="animate-spin" />
                                    )}
                                    Start import
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <Link href={index.url()}>Back</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Job reference
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Use either the slug or the ID in the{' '}
                            <span className="font-medium">job</span> column.
                        </p>
                    </CardHeader>
                    <CardContent>
                        {jobPostings.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No jobs yet. Create a job first.
                            </p>
                        ) : (
                            <div className="max-h-64 overflow-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-3">ID</th>
                                            <th className="py-2 pr-3">Title</th>
                                            <th className="py-2">Slug</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {jobPostings.map((job) => (
                                            <tr key={job.id}>
                                                <td className="py-2 pr-3">
                                                    {job.id}
                                                </td>
                                                <td className="py-2 pr-3">
                                                    {job.title}
                                                </td>
                                                <td className="py-2">
                                                    <button
                                                        type="button"
                                                        className="font-mono text-xs underline-offset-2 hover:underline"
                                                        title="Copy slug"
                                                        onClick={() => {
                                                            void navigator.clipboard?.writeText(
                                                                job.slug,
                                                            );
                                                            toast.success(
                                                                'Slug copied',
                                                            );
                                                        }}
                                                    >
                                                        {job.slug}
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recent imports
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {imports.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No imports yet.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {imports.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex flex-wrap items-center gap-3 py-3 text-sm"
                                    >
                                        <Badge
                                            variant={statusVariant[item.status]}
                                        >
                                            {item.status_label}
                                        </Badge>
                                        <span className="text-muted-foreground">
                                            {item.created_at}
                                        </span>
                                        {item.status === 'completed' && (
                                            <span>
                                                {item.created_count} added ·{' '}
                                                {item.skipped_count} skipped ·{' '}
                                                {item.failed_count} failed (of{' '}
                                                {item.total})
                                                {item.warning_count > 0 &&
                                                    ` · ${item.warning_count} added without a resume`}
                                            </span>
                                        )}
                                        {item.status === 'failed' && (
                                            <span className="text-destructive">
                                                {item.error}
                                            </span>
                                        )}
                                        {item.has_issues && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="ml-auto"
                                                asChild
                                            >
                                                <a href={issues.url(item.id)}>
                                                    <Download />
                                                    Issues CSV
                                                </a>
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
