<?php

namespace App\Ai\Agents;

use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class CandidateRatingAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public JobPosting $jobPosting,
        public ?JobApplication $jobApplication = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $job = $this->jobPosting;

        $salary = $job->min_salary && $job->max_salary
            ? "{$job->min_salary}-{$job->max_salary}"
            : 'not specified';

        return <<<INSTRUCTIONS
            You are an expert technical recruiter screening resumes for a specific job opening.

            You will be given a candidate's resume (as an attachment, or as plain text if the
            original file could not be attached directly). Do two things:

            1. Extract a structured profile from the resume: skills, total years of experience,
               education history, work history, certifications, and a short summary.
            2. Rate how well this specific candidate fits the job below, from 1 (poor fit) to
               10 (excellent fit), with concrete reasoning grounded in the resume and the job's
               actual requirements. Do not assume information that isn't in the resume.
            {$this->recruiterDetailsInstructions()}

            Job: {$job->title}
            Department: {$job->department}
            Location: {$job->location} ({$job->work_mode->label()})
            Employment type: {$job->employment_type->label()}
            Experience level required: {$job->experience_level}
            Salary range: {$salary}

            Description:
            {$job->description}

            Responsibilities:
            {$job->responsibilities}

            Requirements:
            {$job->requirements}
            INSTRUCTIONS;
    }

    /**
     * Recruiter-entered details about the candidate, when any were provided.
     * Gender, date of birth and similar protected attributes are deliberately
     * never sent to the model.
     */
    private function recruiterDetailsInstructions(): string
    {
        $candidate = $this->jobApplication;

        $details = array_filter([
            'Total experience' => $candidate?->total_experience !== null ? "{$candidate->total_experience} years" : null,
            'Relevant experience' => $candidate?->relevant_experience !== null ? "{$candidate->relevant_experience} years" : null,
            'Current designation' => $candidate?->current_designation,
            'Current company' => $candidate?->current_company,
            'Current CTC' => $candidate?->current_ctc,
            'Expected CTC' => $candidate?->expected_ctc,
            'Notice period' => $candidate?->notice_period,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($details === []) {
            return '';
        }

        $lines = implode(PHP_EOL, array_map(
            fn (string $label, mixed $value): string => "- {$label}: {$value}",
            array_keys($details),
            $details,
        ));

        return <<<DETAILS

            The recruiter also entered these details about the candidate:
            {$lines}

            Treat the recruiter's experience figures as more reliable than your own estimate from
            the resume. If total or relevant experience is clearly below or above what the job's
            experience level requires, let that move the score and list it under gaps or strengths.
            If expected CTC is well above the job's salary range, or the notice period is long,
            mention it in the reasoning and gaps but do not change the score because of it.
            DETAILS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'profile' => $schema->object(fn (JsonSchema $schema): array => [
                'skills' => $schema->array()->items($schema->string())
                    ->description('Technical and professional skills mentioned in the resume.'),
                'total_experience_years' => $schema->number()
                    ->description('Best estimate of total professional experience, in years.')
                    ->nullable(),
                'education' => $schema->array()->items(
                    $schema->object(fn (JsonSchema $schema): array => [
                        'degree' => $schema->string(),
                        'institution' => $schema->string(),
                        'year' => $schema->string()->nullable(),
                    ])
                ),
                'work_history' => $schema->array()->items(
                    $schema->object(fn (JsonSchema $schema): array => [
                        'company' => $schema->string(),
                        'title' => $schema->string(),
                        'start' => $schema->string()->nullable(),
                        'end' => $schema->string()->nullable(),
                    ])
                ),
                'certifications' => $schema->array()->items($schema->string()),
                'summary' => $schema->string()
                    ->description('A 2-3 sentence summary of the candidate.'),
            ])->required(),

            'rating' => $schema->object(fn (JsonSchema $schema): array => [
                'score' => $schema->integer()->min(1)->max(10)->required()
                    ->description('Fit for this specific job, 1 (poor) to 10 (excellent).'),
                'reasoning' => $schema->string()->required()
                    ->description('Why this score, grounded in the resume and job requirements.'),
                'strengths' => $schema->array()->items($schema->string()),
                'gaps' => $schema->array()->items($schema->string()),
            ])->required(),
        ];
    }
}
