import { useFlashToast } from '@/hooks/use-flash-toast';
import AdminLayout from '@/layouts/admin-layout';
import JobPostingForm from './job-posting-form';
import type {
    JobPostingCompanyOption,
    JobPostingOption,
} from './job-posting-form';

interface CreateJobPostingProps {
    companies: JobPostingCompanyOption[];
    workModes: JobPostingOption[];
    employmentTypes: JobPostingOption[];
}

export default function CreateJobPosting({
    companies,
    workModes,
    employmentTypes,
}: CreateJobPostingProps) {
    useFlashToast();

    return (
        <AdminLayout title="New Job Posting">
            <div className="mx-auto max-w-3xl">
                <JobPostingForm
                    mode="create"
                    companyOptions={companies}
                    workModes={workModes}
                    employmentTypes={employmentTypes}
                />
            </div>
        </AdminLayout>
    );
}
