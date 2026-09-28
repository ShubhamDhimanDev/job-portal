<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\JobPostingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::resource('job-postings', JobPostingController::class)->except('show');
    Route::post('job-postings/{jobPosting}/publish', [JobPostingController::class, 'publish'])->name('job-postings.publish');
    Route::post('job-postings/{jobPosting}/close', [JobPostingController::class, 'close'])->name('job-postings.close');
    Route::post('job-postings/{jobPosting}/duplicate', [JobPostingController::class, 'duplicate'])->name('job-postings.duplicate');

    Route::resource('companies', CompanyController::class)->except('show');
});
