<?php

use Illuminate\Support\Facades\Route;

//Route::get('/', function () {
//    return view('test');
//});
Route::get('/test', function (){
    return view('test');
});
Route::get('contact', [\App\Http\Controllers\ContactController::class, 'showContactForm']);
Route::get('greeting', [\App\Http\Controllers\GreetingController::class, 'greet']);
Route::get('loginVTB', [\App\Http\Controllers\ApiBankController::class, 'apiVietinbank']);
Route::get('loginVCB', [\App\Http\Controllers\ApiBankController::class, 'apiVietcombank'])->name('api.vietcombank');
