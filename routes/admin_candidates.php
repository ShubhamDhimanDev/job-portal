<?php

use App\Http\Controllers\Admin\CandidateExportController;
use App\Http\Controllers\Admin\CandidateImportController;
use App\Http\Controllers\Admin\JobApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('candidates', [JobApplicationController::class, 'index'])->name('candidates.index');
    Route::get('candidates/create', [JobApplicationController::class, 'create'])->name('candidates.create');
    Route::post('candidates', [JobApplicationController::class, 'store'])->name('candidates.store');
    Route::get('candidates/import', [CandidateImportController::class, 'create'])->name('candidates.import.create');
    Route::post('candidates/import', [CandidateImportController::class, 'store'])->name('candidates.import.store');
    Route::get('candidates/import/template', [CandidateImportController::class, 'template'])->name('candidates.import.template');
    Route::get('candidates/import/{candidateImport}/issues', [CandidateImportController::class, 'issues'])->name('candidates.import.issues');
    Route::delete('candidates/duplicates', [JobApplicationController::class, 'destroyDuplicates'])->name('candidates.duplicates.destroy');
    Route::patch('candidates/{jobApplication}', [JobApplicationController::class, 'update'])->name('candidates.update');
    Route::delete('candidates/{jobApplication}', [JobApplicationController::class, 'destroy'])->name('candidates.destroy');
    Route::get('candidates/{jobApplication}/resume', [JobApplicationController::class, 'resume'])->name('candidates.resume');
    Route::post('candidates/{jobApplication}/resume', [JobApplicationController::class, 'uploadResume'])->name('candidates.resume.upload');
    Route::post('candidates/{jobApplication}/rate', [JobApplicationController::class, 'rate'])->name('candidates.rate');
    Route::get('candidates/export', [CandidateExportController::class, 'download'])->name('candidates.export');
    Route::post('candidates/email-export', [CandidateExportController::class, 'email'])->name('candidates.email-export');
});
