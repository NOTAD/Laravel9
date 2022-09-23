<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test', function (){
    return view('test');
});
Route::get('contact', [\App\Http\Controllers\ContactController::class, 'showContactForm']);
Route::get('greeting', [\App\Http\Controllers\GreetingController::class, 'greet']);