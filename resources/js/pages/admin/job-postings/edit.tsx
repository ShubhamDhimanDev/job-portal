import { router } from '@inertiajs/react';
import { Copy, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFlashToast } from '@/hooks/use-flash-toast';
import AdminLayout from '@/layouts/admin-layout';
import jobPostings from '@/routes/admin/job-postings';
import JobPostingForm from './job-posting-form';
import type {
    JobPostingCompanyOption,
    JobPostingOption,
} from './job-posting-form';

interface EditableJobPosting {
    id: number;
    slug: string;
    company_id: number;
    title: string;
    description: string;
    responsibilities: string | null;
    requirements: string | null;
    location: string;
    work_mode: string;
    employment_type: string;
    experience_level: string | null;
    min_salary: number | null;
    max_salary: number | null;
    salary_negotiable: boolean;
    department: string | null;
    vacancies: number;
    status: string;
    status_label: string;
    application_deadline: string | null;
}

interface EditJobPostingProps {
    jobPosting: EditableJobPosting;
    companies: JobPostingCompanyOption[];
    workModes: JobPostingOption[];
    employmentTypes: JobPostingOption[];
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

export default function EditJobPosting({
    jobPosting,
    companies,
    workModes,
    employmentTypes,
}: EditJobPostingProps) {
    useFlashToast();

    const publish = () => {
        router.post(jobPostings.publish.url(jobPosting.slug));
    };

    const close = () => {
        router.post(jobPostings.close.url(jobPosting.slug));
    };

    const duplicate = () => {
        router.post(jobPostings.duplicate.url(jobPosting.slug));
    };

    const deleteJobPosting = () => {
        if (confirm(`Delete "${jobPosting.title}"? This cannot be undone.`)) {
            router.delete(jobPostings.destroy.url(jobPosting.slug));
        }
    };

    return (
        <AdminLayout title="Edit Job Posting">
            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Badge variant={statusVariant(jobPosting.status)}>
                            {jobPosting.status_label}
                        </Badge>
                        <span className="text-sm text-muted-foreground">
                            {jobPosting.title}
                        </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {jobPosting.status !== 'published' && (
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={publish}
                            >
                                Publish
                            </Button>
                        )}
                        {jobPosting.status !== 'closed' && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={close}
                            >
                                <X className="h-4 w-4" />
                                Close
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={duplicate}
                        >
                            <Copy className="h-4 w-4" />
                            Duplicate
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={deleteJobPosting}
                        >
                            Delete
                        </Button>
                    </div>
                </div>

                <JobPostingForm
                    mode="edit"
                    slug={jobPosting.slug}
                    companyOptions={companies}
                    workModes={workModes}
                    employmentTypes={employmentTypes}
                    defaultValues={{
                        company_id: String(jobPosting.company_id),
                        title: jobPosting.title,
                        description: jobPosting.description,
                        responsibilities: jobPosting.responsibilities ?? '',
                        requirements: jobPosting.requirements ?? '',
                        location: jobPosting.location,
                        work_mode: jobPosting.work_mode,
                        employment_type: jobPosting.employment_type,
                        experience_level: jobPosting.experience_level ?? '',
                        min_salary:
                            jobPosting.min_salary !== null
                                ? String(jobPosting.min_salary)
                                : '',
                        max_salary:
                            jobPosting.max_salary !== null
                                ? String(jobPosting.max_salary)
                                : '',
                        salary_negotiable: jobPosting.salary_negotiable,
                        department: jobPosting.department ?? '',
                        vacancies: String(jobPosting.vacancies),
                        application_deadline:
                            jobPosting.application_deadline ?? '',
                    }}
                />
            </div>
        </AdminLayout>
    );
}
