<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConceptController;

use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\PracticeController;
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
});
