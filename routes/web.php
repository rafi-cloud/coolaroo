<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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