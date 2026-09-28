<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobPosting;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'publishedJobs' => JobPosting::query()->where('status', JobStatus::Published)->count(),
                'draftJobs' => JobPosting::query()->where('status', JobStatus::Draft)->count(),
                'totalCandidates' => JobApplication::query()->count(),
                'newCandidatesThisWeek' => JobApplication::query()
                    ->where('created_at', '>=', Carbon::now()->subWeek())
                    ->count(),
                'shortlistedCandidates' => JobApplication::query()
                    ->where('status', ApplicationStatus::Shortlisted)
                    ->count(),
            ],
        ]);
    }
}
