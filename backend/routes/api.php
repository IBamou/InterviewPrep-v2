<?php

use App\Http\Controllers\Api\ConceptController;
use App\Http\Controllers\Api\DomainController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn () => request()->user());

    // Domains
    Route::get('/domains/archives', [DomainController::class, 'archives']);
    Route::post('/domains/{id}/restore', [DomainController::class, 'restore']);
    Route::delete('/domains/{id}/force', [DomainController::class, 'forceDelete']);
    Route::apiResource('/domains', DomainController::class);

    // Concepts (nested under domains + standalone)
    Route::get('/domains/{domain}/concepts/archives', [ConceptController::class, 'archives']);
    Route::post('/concepts/{id}/restore', [ConceptController::class, 'restore']);
    Route::delete('/concepts/{id}/force', [ConceptController::class, 'forceDelete']);
    Route::apiResource('/domains.concepts', ConceptController::class);
});
