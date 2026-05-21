<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConceptController;
use App\Http\Controllers\Api\ConceptRelationController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\ExplanationController;
use App\Http\Controllers\Api\PracticeController;
use App\Http\Controllers\Api\ProgressionController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn () => new UserResource(request()->user()));
    Route::post('/logout', [AuthController::class, 'logout']);

    // Domains
    Route::get('/domains/archives', [DomainController::class, 'archives']);
    Route::post('/domains/{id}/restore', [DomainController::class, 'restore']);
    Route::delete('/domains/{id}/force', [DomainController::class, 'forceDelete']);
    Route::apiResource('/domains', DomainController::class);

    // Concepts
    Route::get('/domains/{domain}/concepts/archives', [ConceptController::class, 'archives']);
    Route::post('/concepts/{id}/restore', [ConceptController::class, 'restore']);
    Route::delete('/concepts/{id}/force', [ConceptController::class, 'forceDelete']);
    Route::apiResource('/domains.concepts', ConceptController::class);

    // Practice (nested under domain.concept)
    Route::get('/domains/{domain}/concepts/{concept}/practice', [PracticeController::class, 'index']);
    Route::post('/domains/{domain}/concepts/{concept}/practice', [PracticeController::class, 'store']);
    Route::get('/domains/{domain}/concepts/{concept}/practice/{setNumber}', [PracticeController::class, 'show']);
    Route::post('/domains/{domain}/concepts/{concept}/practice/{setNumber}', [PracticeController::class, 'submit']);

    // Explanation
    Route::post('/domains/{domain}/concepts/{concept}/explanation/generate', [ExplanationController::class, 'generate']);
    Route::post('/domains/{domain}/concepts/{concept}/explanation/improve', [ExplanationController::class, 'improve']);
    Route::put('/domains/{domain}/concepts/{concept}/explanation/accept', [ExplanationController::class, 'accept']);

    // Progression
    Route::get('/domains/{domain}/concepts/{concept}/progression', [ProgressionController::class, 'show']);
    Route::get('/domains/{domain}/progression/weak-areas', [ProgressionController::class, 'weakAreas']);

    // Resources
    Route::get('/domains/{domain}/concepts/{concept}/resources', [ResourceController::class, 'index']);
    Route::post('/domains/{domain}/concepts/{concept}/resources', [ResourceController::class, 'store']);
    Route::delete('/domains/{domain}/concepts/{concept}/resources/{resource}', [ResourceController::class, 'destroy']);

    // Concept Relations
    Route::get('/domains/{domain}/concepts/{concept}/relations', [ConceptRelationController::class, 'index']);
    Route::post('/domains/{domain}/concepts/{concept}/relations', [ConceptRelationController::class, 'store']);
    Route::delete('/domains/{domain}/concepts/{concept}/relations/{relation}', [ConceptRelationController::class, 'destroy']);
    Route::post('/domains/{domain}/concepts/{concept}/relations/suggest', [ConceptRelationController::class, 'suggest']);
});
