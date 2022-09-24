<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\APIResources\BankAPI;
class vtb extends Controller
{
    public function apivtb(Request $request){
        $username = $request->input('username');
        $pass = $request->input('pass');
        $acc = $request->input('acc');
        $api = new BankAPI\APIVTB($username,$pass,$acc);
        $api->login();
        return($api->getTransaction("28/05/2022","28/07/2022"));

    }
}
