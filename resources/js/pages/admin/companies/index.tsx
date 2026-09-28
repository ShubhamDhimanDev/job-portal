import { Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEventHandler } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFlashToast } from '@/hooks/use-flash-toast';
import AdminLayout from '@/layouts/admin-layout';
import companies from '@/routes/admin/companies';

interface CompanyRow {
    id: number;
    name: string;
    contact_person: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    website: string | null;
    notes: string | null;
    job_postings_count: number;
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

interface CompaniesIndexProps {
    companies: Paginated<CompanyRow>;
    filters: {
        search: string | null;
    };
}

export default function CompaniesIndex({
    companies: paginatedCompanies,
    filters,
}: CompaniesIndexProps) {
    useFlashToast();

    const errors = usePage().props.errors as Record<string, string> | undefined;
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            companies.index.url(),
            { search: search || undefined },
            { preserveState: true, replace: true },
        );
    };

    const destroy = (id: number, name: string, jobPostingsCount: number) => {
        if (jobPostingsCount > 0) {
            alert(
                `"${name}" has ${jobPostingsCount} job posting(s) and cannot be deleted. Close or reassign those postings first.`,
            );

            return;
        }

        if (confirm(`Delete "${name}"? This cannot be undone.`)) {
            router.delete(companies.destroy.url(id));
        }
    };

    return (
        <AdminLayout title="Companies">
            <div className="space-y-6">
                {errors?.company && (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.company}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <form
                        onSubmit={submitSearch}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search company name…"
                            className="w-64"
                        />
                        <Button type="submit" variant="secondary">
                            Search
                        </Button>
                    </form>

                    <Button asChild>
                        <Link href={companies.create.url()}>
                            <Plus className="h-4 w-4" />
                            New company
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs font-medium text-muted-foreground uppercase">
                            <tr>
                                <th className="px-4 py-3">Name</th>
                                <th className="px-4 py-3">Contact</th>
                                <th className="px-4 py-3">Email</th>
                                <th className="px-4 py-3">Phone</th>
                                <th className="px-4 py-3">Job postings</th>
                                <th className="px-4 py-3 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {paginatedCompanies.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No companies found.
                                    </td>
                                </tr>
                            )}
                            {paginatedCompanies.data.map((company) => (
                                <tr key={company.id}>
                                    <td className="px-4 py-3 font-medium">
                                        <Link
                                            href={companies.edit.url(
                                                company.id,
                                            )}
                                            className="hover:underline"
                                        >
                                            {company.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {company.contact_person ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {company.contact_email ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {company.contact_phone ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {company.job_postings_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                            >
                                                <Link
                                                    href={companies.edit.url(
                                                        company.id,
                                                    )}
                                                    title="Edit"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                title="Delete"
                                                onClick={() =>
                                                    destroy(
                                                        company.id,
                                                        company.name,
                                                        company.job_postings_count,
                                                    )
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

                {paginatedCompanies.links.length > 3 && (
                    <div className="flex flex-wrap items-center gap-1">
                        {paginatedCompanies.links.map((link, index) => (
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
