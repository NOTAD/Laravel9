<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test', function (){
    return view('test');
});
Route::get('/contact', [\App\Http\Controllers\ContactController::class, 'showContactForm']);