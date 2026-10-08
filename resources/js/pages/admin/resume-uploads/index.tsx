import { Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useEffect } from 'react';
import {
    issues,
    show,
} from '@/actions/App/Http/Controllers/Admin/ResumeUploadController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { uploadStatusVariant } from '@/lib/resume-uploads';
import type { PaginatedData, UploadSummary } from '@/lib/resume-uploads';

interface ResumeUploadsIndexProps {
    uploads: PaginatedData<UploadSummary>;
}

export default function ResumeUploadsIndex({
    uploads,
}: ResumeUploadsIndexProps) {
    const isRunning = uploads.data.some(
        (upload) =>
            upload.status === 'pending' || upload.status === 'processing',
    );

    useEffect(() => {
        if (!isRunning) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['uploads'] });
        }, 3000);

        return () => window.clearInterval(timer);
    }, [isRunning]);

    return (
        <AdminLayout title="Upload Reports">
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Resume uploads{' '}
                        <span className="font-normal text-muted-foreground">
                            ({uploads.total})
                        </span>
                    </CardTitle>
                    <p className="text-sm text-muted-foreground">
                        Every resume or ZIP uploaded from the Candidates page,
                        and what happened to each file.
                    </p>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-xs text-muted-foreground">
                                    <th className="pr-3 pb-2 font-medium">
                                        Upload
                                    </th>
                                    <th className="pr-3 pb-2 font-medium">
                                        Job
                                    </th>
                                    <th className="pr-3 pb-2 font-medium">
                                        Status
                                    </th>
                                    <th className="pr-3 pb-2 font-medium">
                                        Result
                                    </th>
                                    <th className="pr-3 pb-2 font-medium" />
                                </tr>
                            </thead>
                            <tbody>
                                {uploads.data.map((upload) => (
                                    <tr
                                        key={upload.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-3 pr-3 align-top">
                                            <Link
                                                href={show.url(upload.id)}
                                                className="font-medium underline-offset-2 hover:underline"
                                            >
                                                {upload.original_filename}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {upload.created_at}
                                                {upload.uploaded_by &&
                                                    ` · ${upload.uploaded_by}`}
                                            </div>
                                        </td>
                                        <td className="py-3 pr-3 align-top">
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
                                            {upload.rate_with_ai && (
                                                <div className="text-xs text-muted-foreground">
                                                    AI rating on
                                                </div>
                                            )}
                                        </td>
                                        <td className="py-3 pr-3 align-top">
                                            <Badge
                                                variant={
                                                    uploadStatusVariant[
                                                        upload.status
                                                    ]
                                                }
                                            >
                                                {upload.status_label}
                                            </Badge>
                                        </td>
                                        <td className="py-3 pr-3 align-top">
                                            {upload.status === 'failed' ? (
                                                <span className="text-destructive">
                                                    {upload.error}
                                                </span>
                                            ) : (
                                                <span>
                                                    {upload.created_count} added
                                                    · {upload.skipped_count}{' '}
                                                    skipped ·{' '}
                                                    {upload.failed_count} failed
                                                    {upload.total > 0 &&
                                                        ` (of ${upload.total})`}
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-3 pr-3 text-right align-top whitespace-nowrap">
                                            {upload.has_issues && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={issues.url(
                                                            upload.id,
                                                        )}
                                                    >
                                                        <Download />
                                                        Issues CSV
                                                    </a>
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}

                                {uploads.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No uploads yet. Use the Upload
                                            button on the Candidates page.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {uploads.links.length > 3 && (
                        <div className="mt-4 flex flex-wrap items-center gap-1">
                            {uploads.links.map((link, i) => (
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
        </AdminLayout>
    );
}
