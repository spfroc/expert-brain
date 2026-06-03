<?php

use App\Http\Controllers\Api\SessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::prefix('v1')->group(function (): void {
    Route::post('/session', [SessionController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/session', [SessionController::class, 'show']);
        Route::delete('/session', [SessionController::class, 'destroy']);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
