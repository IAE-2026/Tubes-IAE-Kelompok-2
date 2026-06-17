<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\BidController; 

Route::prefix('v1')->group(function () {
    Route::middleware('api.key')->group(function () {
        Route::get('/bids', [BidController::class, 'index']);
        Route::get('/bids/highest/{auctionId}', [BidController::class, 'highest']);
        Route::get('/bids/{id}', [BidController::class, 'show']);
    });
    
    Route::middleware('sso.auth')->group(function () {
        Route::post('/bids', [BidController::class, 'store']);
    });
});