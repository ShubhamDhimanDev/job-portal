<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'home')->name('home');

require __DIR__.'/public.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
