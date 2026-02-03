<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'bingo-admin-api',
        'version' => '1.0.0',
    ]);
});

// Public Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected Routes (requires Sanctum authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('update-password', [AuthController::class, 'updatePassword']);
    });

    // Events
    Route::apiResource('events', EventController::class);
    Route::post('events/{event}/start', [EventController::class, 'start']);
    Route::post('events/{event}/finish', [EventController::class, 'finish']);

    // Card Layouts (Etapa 7-8: PDF Layouts)
    Route::apiResource('layouts', CardLayoutController::class);
    Route::post('layouts/{layout}/set-default', [CardLayoutController::class, 'setDefault']);
    Route::post('layouts/{layout}/upload-background', [CardLayoutController::class, 'uploadBackground']);
    Route::put('layouts/{layout}/config', [CardLayoutController::class, 'updateConfig']);

    // Cards & Generation (Etapa 7)
    Route::apiResource('events.cards', CardController::class)->only('index', 'show');
    Route::post('events/{event}/generate-cards', [CardController::class, 'generate']);
    Route::get('events/{event}/generate-status', [CardController::class, 'generateStatus']);
    Route::get('cards/qr/{qr_code}', [CardController::class, 'downloadByQR']);

    // TODO: Additional routes will be added in subsequent etapas
    // Route::post('events/{event}/draw', 'DrawController@draw');
    // Route::get('events/{event}/draws', 'DrawController@index');
    // Route::post('bingo/claim', 'BingoController@claim');
    // Route::get('events/{event}/reports', 'ReportController@generate');
});
