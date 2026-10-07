<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CandidatesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmailCandidatesExportRequest;
use App\Mail\CandidatesExportMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CandidateExportController extends Controller
{
    public function download(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['job_posting_id', 'status', 'date_from', 'date_to', 'search', 'experience_min', 'experience_max', 'salary_basis', 'salary_min', 'salary_max', 'notice_period']);

        return Excel::download(
            new CandidatesExport($filters),
            'candidates-'.now()->format('Y-m-d-His').'.xlsx'
        );
    }

    public function email(EmailCandidatesExportRequest $request): RedirectResponse
    {
        $filters = $request->safe()->only(['job_posting_id', 'status', 'date_from', 'date_to', 'search', 'experience_min', 'experience_max', 'salary_basis', 'salary_min', 'salary_max', 'notice_period']);

        $bytes = Excel::raw(new CandidatesExport($filters), \Maatwebsite\Excel\Excel::XLSX);

        $filename = 'candidates-'.now()->format('Y-m-d-His').'.xlsx';

        Mail::to($request->validated('to'))
            ->cc($request->validated('cc') ?? [])
            ->bcc($request->validated('bcc') ?? [])
            ->send(new CandidatesExportMail(
                emailSubject: $request->validated('subject'),
                messageBody: $request->validated('message'),
                attachmentBytes: $bytes,
                attachmentFilename: $filename,
            ));

        return back()->with('success', 'Candidates export emailed successfully.');
    }
}
