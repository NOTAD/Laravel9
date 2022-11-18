<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/contact', [\App\Http\Controllers\ContactController::class, 'showContactForm']);
Route::get('/greeting', [\App\Http\Controllers\GreetingController::class, 'greet']);
Route::get('/loginVTB', [\App\Http\Controllers\ApiBankController::class, 'apiVietinbank']);
Route::get('/loginVCB', [\App\Http\Controllers\ApiBankController::class, 'apiVietcombank'])->name('api.vietcombank');
Route::get('/transferInVietcombank', [\App\Http\Controllers\ApiBankController::class, 'transferInVietcombank']);

Route::get('/loginMBB', [\App\Http\Controller\ApiBankController::class, 'loginMBB']);