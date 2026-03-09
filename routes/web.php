<?php

use Illuminate\Support\Facades\Route;

// Serve SPA for all non-API routes
Route::get('/{any?}', function () {
    return file_get_contents(public_path('spa.html'));
})->where('any', '^(?!api|horizon|up|docs).*$');
