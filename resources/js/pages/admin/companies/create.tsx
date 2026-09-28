import { useFlashToast } from '@/hooks/use-flash-toast';
import AdminLayout from '@/layouts/admin-layout';
import CompanyForm from './company-form';

export default function CreateCompany() {
    useFlashToast();

    return (
        <AdminLayout title="New Company">
            <div className="mx-auto max-w-2xl">
                <CompanyForm mode="create" />
            </div>
        </AdminLayout>
    );
}
