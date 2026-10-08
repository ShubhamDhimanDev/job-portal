import { Download, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import {
    preview,
    resume,
} from '@/actions/App/Http/Controllers/Admin/JobApplicationController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export interface ResumePreviewCandidate {
    id: number;
    name: string;
    code: string;
}

interface ResumePreviewDialogProps {
    candidate: ResumePreviewCandidate | null;
    onOpenChange: (open: boolean) => void;
}

export function ResumePreviewDialog({
    candidate,
    onOpenChange,
}: ResumePreviewDialogProps) {
    return (
        <Dialog open={candidate !== null} onOpenChange={onOpenChange}>
            <DialogContent className="flex h-[90vh] flex-col gap-3 sm:max-w-5xl">
                {candidate && (
                    <ResumeFrame key={candidate.id} candidate={candidate} />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ResumeFrame({ candidate }: { candidate: ResumePreviewCandidate }) {
    const [loaded, setLoaded] = useState(false);

    return (
        <>
            <DialogHeader>
                <DialogTitle className="flex flex-wrap items-baseline gap-2">
                    {candidate.name}
                    <span className="font-mono text-xs font-normal text-muted-foreground">
                        {candidate.code}
                    </span>
                </DialogTitle>
                <DialogDescription>Resume preview</DialogDescription>
            </DialogHeader>

            <div className="relative min-h-0 flex-1">
                {!loaded && (
                    <div className="absolute inset-0 flex items-center justify-center text-muted-foreground">
                        <LoaderCircle className="animate-spin" />
                    </div>
                )}
                <iframe
                    title={`Resume of ${candidate.name}`}
                    src={preview.url(candidate.id)}
                    onLoad={() => setLoaded(true)}
                    className="size-full rounded-md border bg-white"
                />
            </div>

            <DialogFooter>
                <Button variant="outline" asChild>
                    <a href={resume.url(candidate.id)} download>
                        <Download />
                        Download
                    </a>
                </Button>
            </DialogFooter>
        </>
    );
}
