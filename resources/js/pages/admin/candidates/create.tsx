import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEventHandler } from 'react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Admin/JobApplicationController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

interface CreateCandidateProps {
    jobPostings: JobPostingOption[];
}

const textareaClassName = cn(
    'flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
    'disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm',
);

export default function CreateCandidate({ jobPostings }: CreateCandidateProps) {
    const { data, setData, post, processing, errors } = useForm<{
        job_posting_id: string;
        name: string;
        email: string;
        phone: string;
        cover_note: string;
        resume: File | null;
    }>({
        job_posting_id: '',
        name: '',
        email: '',
        phone: '',
        cover_note: '',
        resume: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(), { forceFormData: true });
    };

    return (
        <AdminLayout title="Add Candidate">
            <form onSubmit={submit} className="mx-auto max-w-2xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Candidate details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
                            <Label>Job</Label>
                            <Select
                                value={data.job_posting_id}
                                onValueChange={(value) =>
                                    setData('job_posting_id', value)
                                }
                            >
                                <SelectTrigger
                                    className="w-full"
                                    aria-invalid={Boolean(
                                        errors.job_posting_id,
                                    )}
                                >
                                    <SelectValue placeholder="Select a job" />
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

                        <div className="space-y-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                aria-invalid={Boolean(errors.name)}
                            />
                            {errors.name && (
                                <p className="text-sm text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                aria-invalid={Boolean(errors.email)}
                            />
                            {errors.email && (
                                <p className="text-sm text-destructive">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                                aria-invalid={Boolean(errors.phone)}
                            />
                            {errors.phone && (
                                <p className="text-sm text-destructive">
                                    {errors.phone}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="resume">Resume</Label>
                            <Input
                                id="resume"
                                type="file"
                                accept=".pdf,.doc,.docx"
                                onChange={(e) =>
                                    setData(
                                        'resume',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                                aria-invalid={Boolean(errors.resume)}
                            />
                            {errors.resume ? (
                                <p className="text-sm text-destructive">
                                    {errors.resume}
                                </p>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    PDF, DOC or DOCX, up to 5MB.
                                </p>
                            )}
                        </div>

                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="cover_note">Notes (optional)</Label>
                            <textarea
                                id="cover_note"
                                className={textareaClassName}
                                value={data.cover_note}
                                onChange={(e) =>
                                    setData('cover_note', e.target.value)
                                }
                                aria-invalid={Boolean(errors.cover_note)}
                            />
                            {errors.cover_note && (
                                <p className="text-sm text-destructive">
                                    {errors.cover_note}
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" disabled={processing}>
                        {processing && (
                            <LoaderCircle className="animate-spin" />
                        )}
                        Add candidate
                    </Button>
                    <Button type="button" variant="outline" asChild>
                        <Link href={index.url()}>Cancel</Link>
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
