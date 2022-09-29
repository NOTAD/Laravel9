<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\APIResources\BankAPI;

class ApiBankController extends Controller
{
    public function apiVietinbank(Request $request){
        $username = $request->input('username');
        $password = $request->input('password');
        $acc = $request->input('acc');
        if (empty($username)) {
            return("Thieu username");
        } elseif (empty($password)){
            return("Thieu password");
        } elseif (empty($acc)){
            return("Thieu STK");
        } else {
        $api = new BankAPI\APIVTB($username,$password,$acc);
        $login = $api->login();
        //$lsgd = $api->getEntitiesAndAccounts();
        $lsgd = $api->getTransaction("2022-09-22","2022-09-24");
        return $lsgd;
        }
    }
    public function apiVietcombank(Request $request){
        // $username = $request->input('username');
        //$acc = $request->input('acc');
        // $password = $request->input('password');
        $username = "0387654818";
        $password = "Notadlehongson1@";
        $acc = "0491000157943";
        
        // $acc = "0491000157943";
        // if (empty($username) || empty($password)) {
        //     return("Thieu data");
        // } else { 
            $Susername = strval($username);
            $Spassword = strval($password);
            $Sacc = strval($acc);

        $api = new BankAPI\Vietcombank($Susername, $Spassword, $Sacc);
        $login = api->getCaptcha();
        return var_dump($login);
        // }
    }
}
