import { X } from 'lucide-react';
import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

const MAX_SKILLS = 50;
const MAX_SKILL_LENGTH = 50;

export interface CandidateSkillsSubject {
    name: string;
    code: string;
    skills: string[];
}

interface SkillBadgesProps {
    skills: string[];
}

export function SkillBadges({ skills }: SkillBadgesProps) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {skills.map((skill, index) => (
                <Badge key={`${skill}-${index}`} variant="secondary">
                    {skill}
                </Badge>
            ))}
        </div>
    );
}

interface SkillsInputProps {
    id: string;
    skills: string[];
    onChange: (skills: string[]) => void;
}

/**
 * Skills as removable chips with a box to add more: Enter or a comma adds
 * what was typed (a pasted list is split on commas and new lines), and
 * Backspace on an empty box removes the last chip.
 */
export function SkillsInput({ id, skills, onChange }: SkillsInputProps) {
    const [draft, setDraft] = useState('');

    function add(raw: string) {
        const next = [...skills];
        const seen = new Set(next.map((skill) => skill.toLowerCase()));

        for (const part of raw.split(/[\n,]/)) {
            const skill = part.trim().replace(/\s+/g, ' ');

            if (
                skill !== '' &&
                !seen.has(skill.toLowerCase()) &&
                next.length < MAX_SKILLS
            ) {
                seen.add(skill.toLowerCase());
                next.push(skill.slice(0, MAX_SKILL_LENGTH));
            }
        }

        if (next.length !== skills.length) {
            onChange(next);
        }

        setDraft('');
    }

    function handleKeyDown(e: KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter' && draft.trim() !== '') {
            e.preventDefault();
            add(draft);
        } else if (e.key === 'Backspace' && draft === '' && skills.length > 0) {
            onChange(skills.slice(0, -1));
        }
    }

    return (
        <div className="space-y-2">
            {skills.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {skills.map((skill) => (
                        <Badge
                            key={skill}
                            variant="secondary"
                            className="gap-1 pr-1"
                        >
                            {skill}
                            <button
                                type="button"
                                aria-label={`Remove ${skill}`}
                                onClick={() =>
                                    onChange(skills.filter((s) => s !== skill))
                                }
                                className="rounded-sm opacity-60 hover:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}
            <Input
                id={id}
                value={draft}
                maxLength={MAX_SKILL_LENGTH * 20}
                placeholder="Type a skill and press Enter"
                disabled={skills.length >= MAX_SKILLS}
                onChange={(e) =>
                    /[\n,]/.test(e.target.value)
                        ? add(e.target.value)
                        : setDraft(e.target.value)
                }
                onKeyDown={handleKeyDown}
                onBlur={() => add(draft)}
            />
        </div>
    );
}

interface CandidateSkillsDialogProps {
    candidate: CandidateSkillsSubject | null;
    onOpenChange: (open: boolean) => void;
}

export function CandidateSkillsDialog({
    candidate,
    onOpenChange,
}: CandidateSkillsDialogProps) {
    return (
        <Dialog open={candidate !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                {candidate && (
                    <>
                        <DialogHeader>
                            <DialogTitle className="flex flex-wrap items-baseline gap-2">
                                Skills
                                <span className="text-sm font-normal text-muted-foreground">
                                    {candidate.name}
                                </span>
                                <span className="font-mono text-xs font-normal text-muted-foreground">
                                    {candidate.code}
                                </span>
                            </DialogTitle>
                            <DialogDescription>
                                {candidate.skills.length}{' '}
                                {candidate.skills.length === 1
                                    ? 'skill'
                                    : 'skills'}{' '}
                                listed for this candidate.
                            </DialogDescription>
                        </DialogHeader>

                        <SkillBadges skills={candidate.skills} />
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
