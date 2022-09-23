<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GreetingController extends Controller
{
    public function greet()
    {
        return "Xin chào";
    }
    public function showContactForm(){
        return view('contact');
    }
}