<?php

use Illuminate\Support\Facades\Route;

// No '/' route yet — the real homepage lands in T030. Hitting '/' 404s until then.

//have to delete this block when the real pages done.
if (app()->isLocal()) {
    Route::prefix('_preview')->group(function () {
        Route::view('public', 'preview.public');
        Route::view('customer', 'preview.customer');
        Route::view('customer-table', 'preview.customer-table');
        Route::view('staff', 'preview.staff');
        Route::view('admin', 'preview.admin');
    });
}