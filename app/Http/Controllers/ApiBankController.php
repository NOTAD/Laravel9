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
        //Time get history
        $time = $request->input('time' , "7");
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
        //Get time
        $endDate = date("Y-m-d");
        $futureDate = mktime(0, 0, 0, date("m"), date("d")-$time, date("Y"));
        $startDate = date("Y-m-d", $futureDate);

        $lsgd = $api->getTransaction($startDate, $endDate);
        //$lsgd = $api->getTransaction("2022-09-22","2022-09-24");
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
        $login = $api->doLogin();
        return ($login);
        // }
    }
}
