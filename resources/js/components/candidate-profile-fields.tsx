import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export interface CandidateProfileValues {
    gender: string;
    date_of_birth: string;
    total_experience: string;
    relevant_experience: string;
    current_company: string;
    industry_type: string;
    current_designation: string;
    current_location: string;
    current_ctc: string;
    expected_ctc: string;
    notice_period: string;
    interview_type: string;
}

export const emptyCandidateProfile: CandidateProfileValues = {
    gender: '',
    date_of_birth: '',
    total_experience: '',
    relevant_experience: '',
    current_company: '',
    industry_type: '',
    current_designation: '',
    current_location: '',
    current_ctc: '',
    expected_ctc: '',
    notice_period: '',
    interview_type: '',
};

export interface EnumOption {
    value: string;
    label: string;
}

interface CandidateProfileFieldsProps {
    idPrefix: string;
    values: CandidateProfileValues;
    errors: Partial<Record<keyof CandidateProfileValues, string>>;
    genders: EnumOption[];
    interviewTypes: EnumOption[];
    onChange: (field: keyof CandidateProfileValues, value: string) => void;
}

type TextFieldKey = Exclude<
    keyof CandidateProfileValues,
    'gender' | 'interview_type'
>;

const textFields: {
    key: TextFieldKey;
    label: string;
    type?: string;
    step?: string;
    placeholder?: string;
}[] = [
    { key: 'date_of_birth', label: 'Date of birth', type: 'date' },
    {
        key: 'total_experience',
        label: 'Total experience (years)',
        type: 'number',
        step: '0.1',
    },
    {
        key: 'relevant_experience',
        label: 'Relevant experience (years)',
        type: 'number',
        step: '0.1',
    },
    { key: 'current_company', label: 'Current company' },
    { key: 'industry_type', label: 'Industry type' },
    { key: 'current_designation', label: 'Current designation' },
    { key: 'current_location', label: 'Current location' },
    {
        key: 'current_ctc',
        label: 'Current CTC',
        type: 'number',
        step: '0.01',
    },
    {
        key: 'expected_ctc',
        label: 'Expected CTC',
        type: 'number',
        step: '0.01',
    },
    {
        key: 'notice_period',
        label: 'Notice period',
        placeholder: 'e.g. 30 days, Immediate',
    },
];

export function CandidateProfileFields({
    idPrefix,
    values,
    errors,
    genders,
    interviewTypes,
    onChange,
}: CandidateProfileFieldsProps) {
    return (
        <>
            <div className="space-y-2">
                <Label>Gender</Label>
                <Select
                    value={values.gender}
                    onValueChange={(value) => onChange('gender', value)}
                >
                    <SelectTrigger
                        className="w-full"
                        aria-invalid={Boolean(errors.gender)}
                    >
                        <SelectValue placeholder="Select gender" />
                    </SelectTrigger>
                    <SelectContent>
                        {genders.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors.gender && (
                    <p className="text-sm text-destructive">{errors.gender}</p>
                )}
            </div>

            {textFields.map((field) => (
                <div key={field.key} className="space-y-2">
                    <Label htmlFor={`${idPrefix}-${field.key}`}>
                        {field.label}
                    </Label>
                    <Input
                        id={`${idPrefix}-${field.key}`}
                        type={field.type}
                        step={field.step}
                        min={field.type === 'number' ? 0 : undefined}
                        placeholder={field.placeholder}
                        value={values[field.key]}
                        onChange={(e) => onChange(field.key, e.target.value)}
                        aria-invalid={Boolean(errors[field.key])}
                    />
                    {errors[field.key] && (
                        <p className="text-sm text-destructive">
                            {errors[field.key]}
                        </p>
                    )}
                </div>
            ))}

            <div className="space-y-2">
                <Label>Interview type</Label>
                <Select
                    value={values.interview_type}
                    onValueChange={(value) => onChange('interview_type', value)}
                >
                    <SelectTrigger
                        className="w-full"
                        aria-invalid={Boolean(errors.interview_type)}
                    >
                        <SelectValue placeholder="Select interview type" />
                    </SelectTrigger>
                    <SelectContent>
                        {interviewTypes.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {errors.interview_type && (
                    <p className="text-sm text-destructive">
                        {errors.interview_type}
                    </p>
                )}
            </div>
        </>
    );
}
