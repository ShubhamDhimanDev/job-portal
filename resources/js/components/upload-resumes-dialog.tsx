import { useForm } from '@inertiajs/react';
import { LoaderCircle, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/Admin/ResumeUploadController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const NO_JOB = 'none';

interface JobOption {
    id: number;
    code: string;
    title: string;
}

interface UploadResumesDialogProps {
    jobPostings: JobOption[];
}

export function UploadResumesDialog({ jobPostings }: UploadResumesDialogProps) {
    const [open, setOpen] = useState(false);
    const [fileInputKey, setFileInputKey] = useState(0);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        clearErrors,
        transform,
    } = useForm<{
        upload: File | null;
        job_posting_id: string;
        rate_with_ai: boolean;
    }>({
        upload: null,
        job_posting_id: NO_JOB,
        rate_with_ai: true,
    });

    const hasJob = data.job_posting_id !== NO_JOB;

    function handleOpenChange(next: boolean) {
        setOpen(next);

        if (!next) {
            reset();
            clearErrors();
            setFileInputKey((key) => key + 1);
        }
    }

    function submit(e: FormEvent) {
        e.preventDefault();

        transform((formData) => ({
            upload: formData.upload,
            job_posting_id:
                formData.job_posting_id === NO_JOB
                    ? undefined
                    : formData.job_posting_id,
            rate_with_ai:
                formData.job_posting_id === NO_JOB
                    ? undefined
                    : formData.rate_with_ai,
        }));

        post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => handleOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline">
                    <Upload />
                    Upload
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Upload resumes</DialogTitle>
                    <DialogDescription>
                        Upload one resume (PDF, DOC or DOCX) or a ZIP of them.
                        Each resume is read automatically and added as a
                        candidate.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="resume-upload-file">
                            Resume or ZIP
                        </Label>
                        <Input
                            key={fileInputKey}
                            id="resume-upload-file"
                            type="file"
                            accept=".pdf,.doc,.docx,.zip"
                            onChange={(e) =>
                                setData('upload', e.target.files?.[0] ?? null)
                            }
                            aria-invalid={Boolean(errors.upload)}
                        />
                        <p className="text-xs text-muted-foreground">
                            Up to 100MB, and each resume under 5MB.
                        </p>
                        {errors.upload && (
                            <p className="text-sm text-destructive">
                                {errors.upload}
                            </p>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Assign to job (optional)</Label>
                        <Select
                            value={data.job_posting_id}
                            onValueChange={(value) =>
                                setData('job_posting_id', value)
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_JOB}>
                                    No job - add as unassigned
                                </SelectItem>
                                {jobPostings.map((job) => (
                                    <SelectItem
                                        key={job.id}
                                        value={String(job.id)}
                                    >
                                        {job.code} · {job.title}
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

                    {hasJob && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="resume-upload-rate"
                                checked={data.rate_with_ai}
                                onCheckedChange={(checked) =>
                                    setData('rate_with_ai', checked === true)
                                }
                            />
                            <Label htmlFor="resume-upload-rate">
                                Automatically rate candidates with AI
                            </Label>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing || !data.upload}
                        >
                            {processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            {processing ? 'Uploading…' : 'Upload'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
