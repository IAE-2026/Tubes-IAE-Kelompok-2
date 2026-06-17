<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VerificationController;


    Route::prefix('v1')->middleware('iae.key')->group(function () {
    
  
    Route::post('/verifications', [VerificationController::class, 'store']);
    Route::put('/verifications/{id}', [VerificationController::class, 'update']);

    Route::get('/verifications', [VerificationController::class, 'index']);
    

    Route::get('/verifications/{id}', [VerificationController::class, 'show']);
    
});