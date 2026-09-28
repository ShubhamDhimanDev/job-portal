import { useFlashToast } from '@/hooks/use-flash-toast';
import AdminLayout from '@/layouts/admin-layout';
import CompanyForm from './company-form';

interface EditableCompany {
    id: number;
    name: string;
    contact_person: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    website: string | null;
    notes: string | null;
}

interface EditCompanyProps {
    company: EditableCompany;
}

export default function EditCompany({ company }: EditCompanyProps) {
    useFlashToast();

    return (
        <AdminLayout title="Edit Company">
            <div className="mx-auto max-w-2xl">
                <CompanyForm
                    mode="edit"
                    companyId={company.id}
                    defaultValues={{
                        name: company.name,
                        contact_person: company.contact_person ?? '',
                        contact_email: company.contact_email ?? '',
                        contact_phone: company.contact_phone ?? '',
                        website: company.website ?? '',
                        notes: company.notes ?? '',
                    }}
                />
            </div>
        </AdminLayout>
    );
}
