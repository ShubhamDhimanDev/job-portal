import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import companies from '@/routes/admin/companies';

export interface CompanyFormValues {
    name: string;
    contact_person: string;
    contact_email: string;
    contact_phone: string;
    website: string;
    notes: string;
}

interface CompanyFormProps {
    mode: 'create' | 'edit';
    companyId?: number;
    defaultValues?: Partial<CompanyFormValues>;
    onSuccess?: () => void;
}

const emptyValues: CompanyFormValues = {
    name: '',
    contact_person: '',
    contact_email: '',
    contact_phone: '',
    website: '',
    notes: '',
};

const textareaClassName = cn(
    'flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
    'disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm',
);

export default function CompanyForm({
    mode,
    companyId,
    defaultValues,
    onSuccess,
}: CompanyFormProps) {
    const { data, setData, post, put, processing, errors } =
        useForm<CompanyFormValues>({
            ...emptyValues,
            ...defaultValues,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (mode === 'create') {
            post(companies.store.url(), { onSuccess });
        } else if (companyId) {
            put(companies.update.url(companyId), { onSuccess });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Company details</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="name">Company name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            aria-invalid={Boolean(errors.name)}
                        />
                        {errors.name && (
                            <p className="text-sm text-destructive">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="contact_person">Contact person</Label>
                        <Input
                            id="contact_person"
                            value={data.contact_person}
                            onChange={(e) =>
                                setData('contact_person', e.target.value)
                            }
                            aria-invalid={Boolean(errors.contact_person)}
                        />
                        {errors.contact_person && (
                            <p className="text-sm text-destructive">
                                {errors.contact_person}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="contact_email">Contact email</Label>
                        <Input
                            id="contact_email"
                            type="email"
                            value={data.contact_email}
                            onChange={(e) =>
                                setData('contact_email', e.target.value)
                            }
                            aria-invalid={Boolean(errors.contact_email)}
                        />
                        {errors.contact_email && (
                            <p className="text-sm text-destructive">
                                {errors.contact_email}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="contact_phone">Contact phone</Label>
                        <Input
                            id="contact_phone"
                            value={data.contact_phone}
                            onChange={(e) =>
                                setData('contact_phone', e.target.value)
                            }
                            aria-invalid={Boolean(errors.contact_phone)}
                        />
                        {errors.contact_phone && (
                            <p className="text-sm text-destructive">
                                {errors.contact_phone}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="website">Website</Label>
                        <Input
                            id="website"
                            type="url"
                            placeholder="https://"
                            value={data.website}
                            onChange={(e) => setData('website', e.target.value)}
                            aria-invalid={Boolean(errors.website)}
                        />
                        {errors.website && (
                            <p className="text-sm text-destructive">
                                {errors.website}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="notes">Internal notes</Label>
                        <textarea
                            id="notes"
                            className={textareaClassName}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            aria-invalid={Boolean(errors.notes)}
                        />
                        {errors.notes && (
                            <p className="text-sm text-destructive">
                                {errors.notes}
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="flex items-center justify-end gap-3">
                <Button type="submit" disabled={processing}>
                    {processing && (
                        <LoaderCircle className="h-4 w-4 animate-spin" />
                    )}
                    {mode === 'create' ? 'Create company' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
