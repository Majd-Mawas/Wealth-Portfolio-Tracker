<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\PortfolioController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
        Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('web');

        Route::get('/portfolios', [PortfolioController::class, 'index']);
        Route::post('/portfolios', [PortfolioController::class, 'store']);

        Route::middleware('portfolio.member')->group(function (): void {
            Route::get('/portfolios/{portfolio}', [PortfolioController::class, 'show']);
            Route::patch('/portfolios/{portfolio}', [PortfolioController::class, 'update']);
            Route::delete('/portfolios/{portfolio}', [PortfolioController::class, 'destroy']);

            Route::scopeBindings()->group(function (): void {
                Route::get('/portfolios/{portfolio}/members', [MembershipController::class, 'index']);
                Route::post('/portfolios/{portfolio}/members', [MembershipController::class, 'store']);
                Route::patch('/portfolios/{portfolio}/members/{membership}', [MembershipController::class, 'update']);
                Route::delete('/portfolios/{portfolio}/members/{membership}', [MembershipController::class, 'destroy']);
            });
        });
    });
});
