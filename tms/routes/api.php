<?php

use Illuminate\Support\Facades\Route;
use Tms\Http\Controllers\IngestController;
use Tms\Http\Controllers\ResolveController;
use Tms\Http\Controllers\UnitController;
use Tms\Http\Middleware\ValidateApiKey;

Route::middleware(ValidateApiKey::class)->group(function () {
    // Ingest: receive entities from PIM
    Route::post('ingest', IngestController::class);

    // Resolve: look up translations by hash
    Route::get('resolve', ResolveController::class);

    // Units: CRUD for translation management
    Route::get('units', [UnitController::class, 'index']);
    Route::get('units/{id}', [UnitController::class, 'show']);
    Route::put('units/{id}/translations/{lang}', [UnitController::class, 'updateTranslation']);

    // Stats & Missing
    Route::get('stats', [UnitController::class, 'stats']);
    Route::get('missing', [UnitController::class, 'missing']);

    // Delete translations
    Route::delete('translations', [UnitController::class, 'deleteTranslations']);

    // Delete all units (purge)
    Route::delete('units', [UnitController::class, 'purgeUnits']);

    // Retranslate
    Route::post('retranslate', [UnitController::class, 'retranslate']);
});
