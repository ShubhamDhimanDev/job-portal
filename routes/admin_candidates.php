<?php

use App\Http\Controllers\Admin\CandidateExportController;
use App\Http\Controllers\Admin\JobApplicationController;
use App\Http\Controllers\Admin\ResumeUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('candidates', [JobApplicationController::class, 'index'])->name('candidates.index');
    Route::get('candidates/create', [JobApplicationController::class, 'create'])->name('candidates.create');
    Route::post('candidates', [JobApplicationController::class, 'store'])->name('candidates.store');
    Route::delete('candidates/duplicates', [JobApplicationController::class, 'destroyDuplicates'])->name('candidates.duplicates.destroy');
    Route::patch('candidates/{jobApplication}', [JobApplicationController::class, 'update'])->name('candidates.update');
    Route::delete('candidates/{jobApplication}', [JobApplicationController::class, 'destroy'])->name('candidates.destroy');
    Route::get('candidates/{jobApplication}/resume', [JobApplicationController::class, 'resume'])->name('candidates.resume');
    Route::get('candidates/{jobApplication}/resume/preview', [JobApplicationController::class, 'preview'])->name('candidates.resume.preview');
    Route::post('candidates/{jobApplication}/resume', [JobApplicationController::class, 'uploadResume'])->name('candidates.resume.upload');
    Route::post('candidates/{jobApplication}/rate', [JobApplicationController::class, 'rate'])->name('candidates.rate');
    Route::get('candidates/export', [CandidateExportController::class, 'download'])->name('candidates.export');
    Route::post('candidates/email-export', [CandidateExportController::class, 'email'])->name('candidates.email-export');

    Route::get('resume-uploads', [ResumeUploadController::class, 'index'])->name('resume-uploads.index');
    Route::post('resume-uploads', [ResumeUploadController::class, 'store'])->name('resume-uploads.store');
    Route::get('resume-uploads/{resumeUpload}', [ResumeUploadController::class, 'show'])->name('resume-uploads.show');
    Route::get('resume-uploads/{resumeUpload}/issues', [ResumeUploadController::class, 'issues'])->name('resume-uploads.issues');
});
