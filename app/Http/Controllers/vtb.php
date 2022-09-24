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
        $login = $api->login();
        $lsgd = $api->getTransaction("24/08/2022","24/09/2022");
        return $lsgd;

    }
}
