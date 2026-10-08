<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\FiltersCandidates;
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
    use FiltersCandidates;

    public function download(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new CandidatesExport($this->candidateFilters($request->query(), withSelection: true)),
            'candidates-'.now()->format('Y-m-d-His').'.xlsx'
        );
    }

    public function email(EmailCandidatesExportRequest $request): RedirectResponse
    {
        $filters = $this->candidateFilters($request->validated(), withSelection: true);

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
