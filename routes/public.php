<?php

use App\Http\Controllers\JobBoardController;
use Illuminate\Support\Facades\Route;

// Public job board routes (listing, detail, apply).
Route::get('/jobs', [JobBoardController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{jobPosting:slug}', [JobBoardController::class, 'show'])->name('jobs.show');
Route::post('/jobs/{jobPosting:slug}/apply', [JobBoardController::class, 'apply'])->name('jobs.apply');
