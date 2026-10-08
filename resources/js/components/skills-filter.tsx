import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type SkillsMatch = 'all' | 'any';

/**
 * Turn the comma separated text typed in the filter into a list of skills:
 * trimmed, without blanks, and without repeats (compared ignoring case).
 */
export function parseSkills(text: string): string[] {
    const seen = new Set<string>();

    return text
        .split(',')
        .map((skill) => skill.trim().replace(/\s+/g, ' '))
        .filter((skill) => {
            const key = skill.toLowerCase();

            if (skill === '' || seen.has(key)) {
                return false;
            }

            seen.add(key);

            return true;
        });
}

interface SkillsFilterProps {
    value: string;
    match: SkillsMatch;
    onChange: (value: string) => void;
    onMatchChange: (match: SkillsMatch) => void;
}

/**
 * Filter candidates by skills typed as free text, separated by commas. Each
 * skill matches part of a candidate's skill too, ignoring case.
 */
export function SkillsFilter({
    value,
    match,
    onChange,
    onMatchChange,
}: SkillsFilterProps) {
    return (
        <div className="flex gap-2">
            <Input
                value={value}
                placeholder="e.g. React, Node.js, AWS"
                aria-label="Skills, separated by commas"
                onChange={(e) => onChange(e.target.value)}
            />

            <Select
                value={match}
                onValueChange={(next) =>
                    onMatchChange(next === 'any' ? 'any' : 'all')
                }
            >
                <SelectTrigger
                    className="w-32 shrink-0"
                    aria-label="Match all or any of the typed skills"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Match all</SelectItem>
                    <SelectItem value="any">Match any</SelectItem>
                </SelectContent>
            </Select>
        </div>
    );
}
