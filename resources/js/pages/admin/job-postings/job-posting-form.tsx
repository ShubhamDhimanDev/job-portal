import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus } from 'lucide-react';
import type { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import companies from '@/routes/admin/companies';
import jobPostings from '@/routes/admin/job-postings';

export interface JobPostingOption {
    value: string;
    label: string;
}

export interface JobPostingCompanyOption {
    id: number;
    name: string;
}

export interface JobPostingFormValues {
    company_id: string;
    title: string;
    description: string;
    responsibilities: string;
    requirements: string;
    location: string;
    work_mode: string;
    employment_type: string;
    experience_level: string;
    min_salary: string;
    max_salary: string;
    salary_negotiable: boolean;
    department: string;
    vacancies: string;
    application_deadline: string;
}

interface JobPostingFormProps {
    mode: 'create' | 'edit';
    slug?: string;
    companyOptions: JobPostingCompanyOption[];
    workModes: JobPostingOption[];
    employmentTypes: JobPostingOption[];
    defaultValues?: Partial<JobPostingFormValues>;
}

const emptyValues: JobPostingFormValues = {
    company_id: '',
    title: '',
    description: '',
    responsibilities: '',
    requirements: '',
    location: '',
    work_mode: '',
    employment_type: '',
    experience_level: '',
    min_salary: '',
    max_salary: '',
    salary_negotiable: false,
    department: '',
    vacancies: '1',
    application_deadline: '',
};

const textareaClassName = cn(
    'flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
    'disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm',
);

export default function JobPostingForm({
    mode,
    slug,
    companyOptions,
    workModes,
    employmentTypes,
    defaultValues,
}: JobPostingFormProps) {
    const { data, setData, post, put, processing, errors } =
        useForm<JobPostingFormValues>({
            ...emptyValues,
            ...defaultValues,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (mode === 'create') {
            post(jobPostings.store.url());
        } else if (slug) {
            put(jobPostings.update.url(slug));
        }
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Job details</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="title">Job title</Label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            aria-invalid={Boolean(errors.title)}
                        />
                        {errors.title && (
                            <p className="text-sm text-destructive">
                                {errors.title}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="company_id">Company</Label>
                            <Link
                                href={companies.create.url()}
                                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground hover:underline"
                            >
                                <Plus className="h-3 w-3" />
                                Add company
                            </Link>
                        </div>
                        <Select
                            value={data.company_id}
                            onValueChange={(value) =>
                                setData('company_id', value)
                            }
                        >
                            <SelectTrigger
                                id="company_id"
                                className="w-full"
                                aria-invalid={Boolean(errors.company_id)}
                            >
                                <SelectValue placeholder="Select a company" />
                            </SelectTrigger>
                            <SelectContent>
                                {companyOptions.map((company) => (
                                    <SelectItem
                                        key={company.id}
                                        value={String(company.id)}
                                    >
                                        {company.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.company_id && (
                            <p className="text-sm text-destructive">
                                {errors.company_id}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="location">Location</Label>
                        <Input
                            id="location"
                            value={data.location}
                            onChange={(e) =>
                                setData('location', e.target.value)
                            }
                            aria-invalid={Boolean(errors.location)}
                        />
                        {errors.location && (
                            <p className="text-sm text-destructive">
                                {errors.location}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="work_mode">Work mode</Label>
                        <Select
                            value={data.work_mode}
                            onValueChange={(value) =>
                                setData('work_mode', value)
                            }
                        >
                            <SelectTrigger
                                id="work_mode"
                                className="w-full"
                                aria-invalid={Boolean(errors.work_mode)}
                            >
                                <SelectValue placeholder="Select work mode" />
                            </SelectTrigger>
                            <SelectContent>
                                {workModes.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.work_mode && (
                            <p className="text-sm text-destructive">
                                {errors.work_mode}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="employment_type">Employment type</Label>
                        <Select
                            value={data.employment_type}
                            onValueChange={(value) =>
                                setData('employment_type', value)
                            }
                        >
                            <SelectTrigger
                                id="employment_type"
                                className="w-full"
                                aria-invalid={Boolean(errors.employment_type)}
                            >
                                <SelectValue placeholder="Select employment type" />
                            </SelectTrigger>
                            <SelectContent>
                                {employmentTypes.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.employment_type && (
                            <p className="text-sm text-destructive">
                                {errors.employment_type}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="department">Department</Label>
                        <Input
                            id="department"
                            value={data.department}
                            onChange={(e) =>
                                setData('department', e.target.value)
                            }
                            aria-invalid={Boolean(errors.department)}
                        />
                        {errors.department && (
                            <p className="text-sm text-destructive">
                                {errors.department}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="experience_level">
                            Experience level
                        </Label>
                        <Input
                            id="experience_level"
                            placeholder="e.g. 2-4 years"
                            value={data.experience_level}
                            onChange={(e) =>
                                setData('experience_level', e.target.value)
                            }
                            aria-invalid={Boolean(errors.experience_level)}
                        />
                        {errors.experience_level && (
                            <p className="text-sm text-destructive">
                                {errors.experience_level}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="vacancies">Vacancies</Label>
                        <Input
                            id="vacancies"
                            type="number"
                            min={1}
                            value={data.vacancies}
                            onChange={(e) =>
                                setData('vacancies', e.target.value)
                            }
                            aria-invalid={Boolean(errors.vacancies)}
                        />
                        {errors.vacancies && (
                            <p className="text-sm text-destructive">
                                {errors.vacancies}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="min_salary">Minimum salary</Label>
                        <Input
                            id="min_salary"
                            type="number"
                            min={0}
                            value={data.min_salary}
                            onChange={(e) =>
                                setData('min_salary', e.target.value)
                            }
                            aria-invalid={Boolean(errors.min_salary)}
                        />
                        {errors.min_salary && (
                            <p className="text-sm text-destructive">
                                {errors.min_salary}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="max_salary">Maximum salary</Label>
                        <Input
                            id="max_salary"
                            type="number"
                            min={0}
                            value={data.max_salary}
                            onChange={(e) =>
                                setData('max_salary', e.target.value)
                            }
                            aria-invalid={Boolean(errors.max_salary)}
                        />
                        {errors.max_salary && (
                            <p className="text-sm text-destructive">
                                {errors.max_salary}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="application_deadline">
                            Application deadline
                        </Label>
                        <Input
                            id="application_deadline"
                            type="date"
                            value={data.application_deadline}
                            onChange={(e) =>
                                setData('application_deadline', e.target.value)
                            }
                            aria-invalid={Boolean(errors.application_deadline)}
                        />
                        {errors.application_deadline && (
                            <p className="text-sm text-destructive">
                                {errors.application_deadline}
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-2 pt-6">
                        <Checkbox
                            id="salary_negotiable"
                            checked={data.salary_negotiable}
                            onCheckedChange={(checked) =>
                                setData('salary_negotiable', checked === true)
                            }
                        />
                        <Label
                            htmlFor="salary_negotiable"
                            className="font-normal"
                        >
                            Salary is negotiable
                        </Label>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Description</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="description">Job description</Label>
                        <textarea
                            id="description"
                            className={textareaClassName}
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            aria-invalid={Boolean(errors.description)}
                        />
                        {errors.description && (
                            <p className="text-sm text-destructive">
                                {errors.description}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="responsibilities">
                            Responsibilities
                        </Label>
                        <textarea
                            id="responsibilities"
                            className={textareaClassName}
                            value={data.responsibilities}
                            onChange={(e) =>
                                setData('responsibilities', e.target.value)
                            }
                            aria-invalid={Boolean(errors.responsibilities)}
                        />
                        {errors.responsibilities && (
                            <p className="text-sm text-destructive">
                                {errors.responsibilities}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="requirements">Requirements</Label>
                        <textarea
                            id="requirements"
                            className={textareaClassName}
                            value={data.requirements}
                            onChange={(e) =>
                                setData('requirements', e.target.value)
                            }
                            aria-invalid={Boolean(errors.requirements)}
                        />
                        {errors.requirements && (
                            <p className="text-sm text-destructive">
                                {errors.requirements}
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="flex items-center justify-end gap-3">
                <Button type="button" variant="outline" asChild>
                    <Link href={jobPostings.index.url()}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && (
                        <LoaderCircle className="h-4 w-4 animate-spin" />
                    )}
                    {mode === 'create' ? 'Create job posting' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
