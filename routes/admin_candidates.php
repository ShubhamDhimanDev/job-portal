<?php

use App\Http\Controllers\Admin\CandidateExportController;
use App\Http\Controllers\Admin\JobApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('candidates', [JobApplicationController::class, 'index'])->name('candidates.index');
    Route::patch('candidates/{jobApplication}', [JobApplicationController::class, 'update'])->name('candidates.update');
    Route::get('candidates/{jobApplication}/resume', [JobApplicationController::class, 'resume'])->name('candidates.resume');
    Route::post('candidates/{jobApplication}/rate', [JobApplicationController::class, 'rate'])->name('candidates.rate');
    Route::get('candidates/export', [CandidateExportController::class, 'download'])->name('candidates.export');
    Route::post('candidates/email-export', [CandidateExportController::class, 'email'])->name('candidates.email-export');
});
