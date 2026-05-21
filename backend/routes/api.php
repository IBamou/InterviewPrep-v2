<?php

use App\Http\Controllers\Api\DomainController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn () => request()->user());

    Route::get('/domains/archives', [DomainController::class, 'archives']);
    Route::post('/domains/{id}/restore', [DomainController::class, 'restore']);
    Route::delete('/domains/{id}/force', [DomainController::class, 'forceDelete']);
    Route::apiResource('/domains', DomainController::class);
});
