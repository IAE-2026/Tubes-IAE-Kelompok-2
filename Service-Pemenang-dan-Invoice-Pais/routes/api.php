<?php

use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\WinnerController;
use App\Http\Controllers\TestSwaggerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Swagger Test Route
|--------------------------------------------------------------------------
*/

Route::get('/test', [TestSwaggerController::class, 'test']);

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    Route::get('/health', function () {
        return response()->json([
            'status'  => 'success',
            'message' => 'Invoice-Winner Service is running.',
        ]);
    });

    Route::middleware('auth.sso')->group(function () {
        Route::prefix('winners')->group(function () {
            Route::get('/', [WinnerController::class, 'index'])
                ->name('winners.index');

            Route::get('/{id}', [WinnerController::class, 'show'])
                ->name('winners.show');
        });

        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])
                ->name('invoices.index');

            Route::post('/', [InvoiceController::class, 'store'])
                ->name('invoices.store');

            Route::get('/{id}', [InvoiceController::class, 'show'])
                ->name('invoices.show');
        });
    });
});