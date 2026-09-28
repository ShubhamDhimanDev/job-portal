<?php

namespace App\Ai\Agents;

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

    public function __construct(public JobPosting $jobPosting) {}

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
