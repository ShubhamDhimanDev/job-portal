import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { useEffect } from 'react';
import {
    index,
    issues,
} from '@/actions/App/Http/Controllers/Admin/ResumeUploadController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { uploadStatusVariant } from '@/lib/resume-uploads';
import type { PaginatedData, UploadSummary } from '@/lib/resume-uploads';

interface UploadItem {
    id: number;
    filename: string;
    candidate_code: string | null;
    status: 'pending' | 'created' | 'skipped' | 'failed';
    status_label: string;
    name: string | null;
    email: string | null;
    reason: string | null;
}

interface ResumeUploadShowProps {
    upload: UploadSummary;
    items: PaginatedData<UploadItem>;
}

const itemVariant: Record<
    UploadItem['status'],
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    created: 'default',
    skipped: 'secondary',
    failed: 'destructive',
};

export default function ResumeUploadShow({
    upload,
    items,
}: ResumeUploadShowProps) {
    const isRunning =
        upload.status === 'pending' || upload.status === 'processing';

    useEffect(() => {
        if (!isRunning) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['upload', 'items'] });
        }, 3000);

        return () => window.clearInterval(timer);
    }, [isRunning]);

    return (
        <AdminLayout title="Upload Report">
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center gap-3">
                            <CardTitle className="text-base">
                                {upload.original_filename}
                            </CardTitle>
                            <Badge variant={uploadStatusVariant[upload.status]}>
                                {upload.status_label}
                            </Badge>
                            <div className="ml-auto flex gap-2">
                                {upload.has_issues && (
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={issues.url(upload.id)}>
                                            <Download />
                                            Issues CSV
                                        </a>
                                    </Button>
                                )}
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={index.url()}>
                                        <ArrowLeft />
                                        All uploads
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-1 text-sm">
                        <p className="text-muted-foreground">
                            {upload.created_at}
                            {upload.uploaded_by && ` · ${upload.uploaded_by}`}
                        </p>
                        <p>
                            Job:{' '}
                            {upload.job_title ? (
                                <>
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {upload.job_code}
                                    </span>{' '}
                                    {upload.job_title}
                                </>
                            ) : (
                                <span className="text-muted-foreground">
                                    Unassigned
                                </span>
                            )}
                            {upload.rate_with_ai && ' · AI rating on'}
                        </p>
                        {upload.status === 'failed' ? (
                            <p className="text-destructive">{upload.error}</p>
                        ) : (
                            <p>
                                {upload.created_count} added ·{' '}
                                {upload.skipped_count} skipped ·{' '}
                                {upload.failed_count} failed
                                {upload.total > 0 && ` (of ${upload.total})`}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Files</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="pr-3 pb-2 font-medium">
                                            File
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Candidate
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Result
                                        </th>
                                        <th className="pr-3 pb-2 font-medium">
                                            Details
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-3 align-top break-all">
                                                {item.filename}
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                {item.name ?? '—'}
                                                {item.candidate_code && (
                                                    <span className="ml-2 font-mono text-xs text-muted-foreground">
                                                        {item.candidate_code}
                                                    </span>
                                                )}
                                                {item.email && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.email}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-3 pr-3 align-top">
                                                <Badge
                                                    variant={
                                                        itemVariant[item.status]
                                                    }
                                                >
                                                    {item.status_label}
                                                </Badge>
                                            </td>
                                            <td className="py-3 pr-3 align-top text-muted-foreground">
                                                {item.reason ?? '—'}
                                            </td>
                                        </tr>
                                    ))}

                                    {items.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                {isRunning
                                                    ? 'Reading the upload…'
                                                    : 'No files were processed.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {items.links.length > 3 && (
                            <div className="mt-4 flex flex-wrap items-center gap-1">
                                {items.links.map((link, i) => (
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
                                                { preserveState: true },
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
