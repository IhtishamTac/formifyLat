<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FormController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });
    Route::middleware(['auth:sanctum'])->group(function () {
     Route::resource('forms', FormController::class);
     Route::post('forms/{slug}/questions', [FormController::class, 'addQuestion']);
     Route::delete('forms/{slug}/questions/{question_id}', [FormController::class, 'removeQuestion']);
     Route::post('forms/{slug}/responses', [FormController::class, 'addResponse']);
     Route::get('forms/{slug}/responses', [FormController::class, 'getResponses']);
    });
});