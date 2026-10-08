import { ChevronDown, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export interface SkillOption {
    name: string;
    count: number;
}

export type SkillsMatch = 'all' | 'any';

const MAX_LISTED = 200;

interface SkillsFilterProps {
    options: SkillOption[];
    selected: string[];
    match: SkillsMatch;
    onChange: (skills: string[]) => void;
    onMatchChange: (match: SkillsMatch) => void;
}

/**
 * Pick several skills to filter candidates by, from the skills candidates
 * actually have. Skills are compared without regard to case.
 */
export function SkillsFilter({
    options,
    selected,
    match,
    onChange,
    onMatchChange,
}: SkillsFilterProps) {
    const [query, setQuery] = useState('');
    const searchRef = useRef<HTMLInputElement>(null);

    const isSelected = (name: string) =>
        selected.some((skill) => skill.toLowerCase() === name.toLowerCase());

    // Skills chosen from a link or an old URL stay selectable even if no
    // candidate has them any more.
    const known = new Set(options.map((option) => option.name.toLowerCase()));
    const all = [
        ...options,
        ...selected
            .filter((skill) => !known.has(skill.toLowerCase()))
            .map((name) => ({ name, count: 0 })),
    ];

    const needle = query.trim().toLowerCase();
    const matching = all.filter((option) =>
        option.name.toLowerCase().includes(needle),
    );

    function toggle(name: string) {
        onChange(
            isSelected(name)
                ? selected.filter(
                      (skill) => skill.toLowerCase() !== name.toLowerCase(),
                  )
                : [...selected, name],
        );
    }

    function handleSearchKeyDown(e: KeyboardEvent<HTMLInputElement>) {
        // Typing in the box must not trigger the menu's type-to-select.
        if (e.key.length === 1 || e.key === 'Backspace') {
            e.stopPropagation();
        }
    }

    return (
        <div className="space-y-2">
            <div className="flex gap-2">
                <DropdownMenu
                    onOpenChange={(open) => {
                        if (open) {
                            // Wait for the menu to take focus, then move it to the search box.
                            window.setTimeout(
                                () => searchRef.current?.focus(),
                                0,
                            );
                        } else {
                            setQuery('');
                        }
                    }}
                >
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-w-0 flex-1 justify-between font-normal"
                        >
                            <span className="truncate">
                                {selected.length === 0
                                    ? 'Any skills'
                                    : `${selected.length} selected`}
                            </span>
                            <ChevronDown className="opacity-50" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-72 p-0">
                        <div className="border-b p-2">
                            <Input
                                ref={searchRef}
                                value={query}
                                placeholder="Search skills"
                                aria-label="Search skills"
                                onChange={(e) => setQuery(e.target.value)}
                                onKeyDown={handleSearchKeyDown}
                            />
                        </div>
                        <div className="max-h-64 overflow-y-auto p-1">
                            {matching.slice(0, MAX_LISTED).map((option) => (
                                <DropdownMenuCheckboxItem
                                    key={option.name.toLowerCase()}
                                    checked={isSelected(option.name)}
                                    onCheckedChange={() => toggle(option.name)}
                                    onSelect={(e) => e.preventDefault()}
                                >
                                    <span className="truncate">
                                        {option.name}
                                    </span>
                                    {option.count > 0 && (
                                        <span className="ml-auto pl-2 text-xs text-muted-foreground">
                                            {option.count}
                                        </span>
                                    )}
                                </DropdownMenuCheckboxItem>
                            ))}
                            {matching.length === 0 && (
                                <p className="px-2 py-3 text-center text-sm text-muted-foreground">
                                    {all.length === 0
                                        ? 'No candidate has skills yet.'
                                        : 'No skills match your search.'}
                                </p>
                            )}
                            {matching.length > MAX_LISTED && (
                                <p className="px-2 py-2 text-center text-xs text-muted-foreground">
                                    Showing {MAX_LISTED} of {matching.length} -
                                    search to narrow down.
                                </p>
                            )}
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Select
                    value={match}
                    onValueChange={(value) =>
                        onMatchChange(value === 'any' ? 'any' : 'all')
                    }
                >
                    <SelectTrigger
                        className="w-32 shrink-0"
                        aria-label="Match all or any of the chosen skills"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Match all</SelectItem>
                        <SelectItem value="any">Match any</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            {selected.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5">
                    {selected.map((skill) => (
                        <Badge
                            key={skill.toLowerCase()}
                            variant="secondary"
                            className="gap-1 pr-1"
                        >
                            {skill}
                            <button
                                type="button"
                                aria-label={`Remove ${skill}`}
                                onClick={() => toggle(skill)}
                                className="rounded-sm opacity-60 hover:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                    <button
                        type="button"
                        onClick={() => onChange([])}
                        className="text-xs text-muted-foreground underline-offset-2 hover:underline"
                    >
                        Clear
                    </button>
                </div>
            )}
        </div>
    );
}
