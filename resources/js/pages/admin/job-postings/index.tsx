import { Link, router } from '@inertiajs/react';
import { Copy, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEventHandler } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFlashToast } from '@/hooks/use-flash-toast';
import AdminLayout from '@/layouts/admin-layout';
import jobPostings from '@/routes/admin/job-postings';

interface JobPostingRow {
    id: number;
    slug: string;
    title: string;
    company: string;
    location: string;
    work_mode: string;
    employment_type: string;
    status: string;
    status_label: string;
    vacancies: number;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

interface JobPostingsIndexProps {
    jobPostings: Paginated<JobPostingRow>;
    filters: {
        status: string | null;
        search: string | null;
    };
    statuses: { value: string; label: string }[];
}

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'published') {
        return 'default';
    }

    if (status === 'closed') {
        return 'outline';
    }

    return 'secondary';
}

export default function JobPostingsIndex({
    jobPostings: paginatedJobPostings,
    filters,
    statuses,
}: JobPostingsIndexProps) {
    useFlashToast();

    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (overrides: { status?: string; search?: string }) => {
        router.get(
            jobPostings.index.url(),
            {
                status: overrides.status ?? filters.status ?? undefined,
                search: overrides.search ?? search ?? undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        applyFilters({ search });
    };

    const publish = (slug: string) => {
        router.post(jobPostings.publish.url(slug));
    };

    const close = (slug: string) => {
        router.post(jobPostings.close.url(slug));
    };

    const duplicate = (slug: string) => {
        router.post(jobPostings.duplicate.url(slug));
    };

    const destroy = (slug: string, title: string) => {
        if (confirm(`Delete "${title}"? This cannot be undone.`)) {
            router.delete(jobPostings.destroy.url(slug));
        }
    };

    return (
        <AdminLayout title="Job Postings">
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <form
                        onSubmit={submitSearch}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search title, location, company…"
                            className="w-64"
                        />
                        <Select
                            value={filters.status ?? 'all'}
                            onValueChange={(value) =>
                                applyFilters({
                                    status: value === 'all' ? '' : value,
                                })
                            }
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                {statuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button type="submit" variant="secondary">
                            Search
                        </Button>
                    </form>

                    <Button asChild>
                        <Link href={jobPostings.create.url()}>
                            <Plus className="h-4 w-4" />
                            New job posting
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs font-medium text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3">Title</th>
                                <th className="px-4 py-3">Company</th>
                                <th className="px-4 py-3">Location</th>
                                <th className="px-4 py-3">Type</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Posted</th>
                                <th className="px-4 py-3 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {paginatedJobPostings.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No job postings found.
                                    </td>
                                </tr>
                            )}
                            {paginatedJobPostings.data.map((job) => (
                                <tr key={job.id} className="align-top">
                                    <td className="px-4 py-3 font-medium">
                                        <Link
                                            href={jobPostings.edit.url(
                                                job.slug,
                                            )}
                                            className="hover:underline"
                                        >
                                            {job.title}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {job.company}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {job.location}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {job.employment_type} · {job.work_mode}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={statusVariant(job.status)}
                                        >
                                            {job.status_label}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {job.created_at}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                            >
                                                <Link
                                                    href={jobPostings.edit.url(
                                                        job.slug,
                                                    )}
                                                    title="Edit"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            {job.status !== 'published' && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    title="Publish"
                                                    onClick={() =>
                                                        publish(job.slug)
                                                    }
                                                >
                                                    Publish
                                                </Button>
                                            )}
                                            {job.status !== 'closed' && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    title="Close"
                                                    onClick={() =>
                                                        close(job.slug)
                                                    }
                                                >
                                                    <X className="h-4 w-4" />
                                                </Button>
                                            )}
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                title="Duplicate"
                                                onClick={() =>
                                                    duplicate(job.slug)
                                                }
                                            >
                                                <Copy className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                title="Delete"
                                                onClick={() =>
                                                    destroy(job.slug, job.title)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {paginatedJobPostings.links.length > 3 && (
                    <div className="flex flex-wrap items-center gap-1">
                        {paginatedJobPostings.links.map((link, index) => (
                            <Button
                                key={index}
                                asChild={link.url !== null}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={link.url === null}
                            >
                                {link.url !== null ? (
                                    <Link
                                        href={link.url}
                                        preserveState
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
