<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CandidateImportStatus;
use App\Exports\CandidateImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportCandidatesRequest;
use App\Jobs\ProcessCandidateImport;
use App\Models\CandidateImport;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateImportController extends Controller
{
    public function create(): Response
    {
        $imports = CandidateImport::query()
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (CandidateImport $import): array => [
                'id' => $import->id,
                'status' => $import->status->value,
                'status_label' => $import->status->label(),
                'total' => $import->total,
                'created_count' => $import->created_count,
                'skipped_count' => $import->skipped_count,
                'failed_count' => $import->failed_count,
                'has_issues' => count($import->issues ?? []) > 0,
                'warning_count' => collect($import->issues ?? [])->where('type', 'warning')->count(),
                'error' => $import->error,
                'created_at' => $import->created_at?->format('M j, Y g:i A'),
            ]);

        return Inertia::render('admin/candidates/import', [
            'imports' => $imports,
            'jobPostings' => JobPosting::query()
                ->orderBy('title')
                ->get(['id', 'title', 'slug']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(ImportCandidatesRequest $request): RedirectResponse
    {
        $directory = 'imports/'.Str::uuid();

        $candidateImport = CandidateImport::query()->create([
            'user_id' => $request->user()->id,
            'status' => CandidateImportStatus::Pending,
            'rate_with_ai' => $request->boolean('rate_with_ai', true),
            'spreadsheet_path' => $request->file('spreadsheet')->storeAs(
                $directory,
                'candidates.'.$request->file('spreadsheet')->getClientOriginalExtension(),
                'local',
            ),
            'archive_path' => $request->file('archive')?->storeAs($directory, 'resumes.zip', 'local'),
        ]);

        ProcessCandidateImport::dispatch($candidateImport);

        return back()->with('success', 'Import started - results will appear below shortly.');
    }

    public function template(): BinaryFileResponse
    {
        return (new CandidateImportTemplateExport)->download('candidate-import-template.xlsx');
    }

    public function issues(CandidateImport $candidateImport): StreamedResponse
    {
        return response()->streamDownload(function () use ($candidateImport): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['row', 'type', 'name', 'email', 'reason']);

            foreach ($candidateImport->issues ?? [] as $issue) {
                fputcsv($handle, [$issue['row'], $issue['type'], $issue['name'], $issue['email'], $issue['reason']]);
            }

            fclose($handle);
        }, "candidate-import-{$candidateImport->id}-issues.csv", ['Content-Type' => 'text/csv']);
    }
}
