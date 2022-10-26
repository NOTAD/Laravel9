<?php

namespace App\APIResources\BankAPI;

use Carbon\Carbon;
use GuzzleHttp\Client;

class MBB
{
    protected $account;
    protected $_timeout = 15;
    protected $captchaCode = "";
    protected $captchaImage = "";
    protected $client;
	  protected $captchaKey = "084d4c96cf5a4829e5641c55a1148053";
    public function __construct($account)
    {
        $this->account = (object) $account;
        if(!$this->account->imei){
            $this->account->imei     = $this->generateImei();
        }
        $this->account->status   = 0;
        $this->client = new Client(['http_errors' => false]);

    }

    public function solveCaptcha(){
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', 'https://api.tungduy.com/api/captcha/mb', array(
            'json' => array(
                'base64'    => $this->captchaImage,
				'apikey' => $this->captchaKey
            ),
            'timeout' => $this->_timeout,
            'headers'     => ['Content-Type'      => 'application/json;'],
        ));
        $data = json_decode($res->getBody());
        $this->captchaCode = $data->data;
        return true;
    }
    public function getCaptcha(){
        try {
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail-web-internetbankingms/getCaptchaImage', array(
                'json' => array(
                    'sessionId'    => "",
                    'refNo'     => date('YmdHms'),
                    'deviceIdCommon' => $this->account->imei
                ),
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault(),
            ));
            $data = json_decode($res->getBody());
            $this->captchaImage = $data->imageString;
            $this->solveCaptcha();
            return $data;
        } catch (\Throwable $e) {
            dd($e);
        }

    }
    public function doLogin()
    {

        try {
            $this->getCaptcha();
            $params = array(
                'userId'    => $this->account->username,
                'password'  => md5($this->account->password),
                'refNo'     => $this->ref_no(),
                'deviceIdCommon' => $this->account->imei,
                'captcha' => $this->captchaCode
            );
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail_web/internetbanking/doLogin', array(
                'json' => $params,
                'timeout' => $this->_timeout,
                'headers' => $this->headerDefault(),
            ));
            $data = json_decode($res->getBody());
            if($data->result->message !=  "Capcha code is invalid"){
                file_put_contents(storage_path("captcha/mb/".rand(111111111,999999999)."_".rand(111111111,999999999)."_".$this->captchaCode.".png"),base64_decode($this->captchaImage));
            }
            return $data;
        } catch (\Throwable $e) {
            dd($e);
        }
    }

    public function getBalance()
    {
        try {
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail-web-accountms/getBalance', array(
                'json' => array(
                    'sessionId'     => $this->account->session_id,
                    'refNo'         => $this->ref_no(),
                    'deviceIdCommon' => $this->account->imei,
                ),
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault()
            ));
            return json_decode($res->getBody());
        } catch (\Throwable $e) {
        }
        return false;
    }

    public function getList()
    {
        try {
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail_web/card/getList', array(
                'json' => array(
                    'sessionId'     => $this->account->session_id,
                    'refNo'         => $this->ref_no(),
                    'deviceIdCommon' => $this->account->imei,
                ),
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault()
            ));
            return json_decode($res->getBody());
        } catch (\Throwable $e) {

        }
        return false;
    }

    public function getTransactionHistory($days)
    {
        $from_date = Carbon::now()->subDays($days)->format("d/m/Y");
        $to_date = Carbon::now()->format("d/m/Y");
        try {
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail_web/common/getTransactionHistory', array(
                'json' => array(
                    'accountNo'     => $this->account->account_no,
                    'fromDate'      => $from_date,
                    'historyNumber' => '',
                    'historyType'   => 'DATE_RANGE',
                    'toDate'        => $to_date,
                    'type'          => 'ACCOUNT',
                    'sessionId'     => $this->account->session_id,
                    'refNo'         => $this->ref_no(),
                    'deviceIdCommon' => $this->account->imei
                ),
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault()
            ));
            return json_decode($res->getBody());
        } catch (\Throwable $e) {

        }
        return false;
    }
    public function getListBank(){
        try {
            $params = array(
                'sessionId'     => $this->account->session_id,
                'refNo'         => $this->ref_no(),
                'deviceIdCommon' => $this->account->imei,
            );
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail_web/common/getBankList', array(
                'json' => $params,
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault()
            ));
            return json_decode($res->getBody());
        } catch (\Throwable $e) {

        }
        return false;
    }
    public function getNameBank($bankID,$account_number){

        try {
            $type = "FAST";
            if($bankID == "970422"){
                $type = "INHOUSE";
            }
            $params = array(
                "bankCode" => $bankID,
                "creditAccount" => $account_number,
                "creditAccountType" => "ACCOUNT",
                "debitAccount" => $this->account->account_no,
                "remark" => "",
                "type" => $type,
                'sessionId'     => $this->account->session_id,
                'refNo'         => $this->ref_no(),
                'deviceIdCommon' => $this->account->imei,
            );
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail_web/transfer/inquiryAccountName', array(
                'json' => $params,
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault()
            ));
            return json_decode($res->getBody());
        } catch (\Throwable $e) {

        }
        return false;
    }

    public function createTranfer($bankID,$account_number,$name,$amount,$message){
        try {
            if($bankID == "970422"){
                $params = array(
                    "benBankCd" => $bankID,
                    "benAccountNumber" => $account_number,
                    "benAccountName" => $name,
                    "destType" => "ACCOUNT",
                    "srcAccountNumber" => $this->account->account_no,
                    "message" => $message,
                    "amount" => $amount,
                    "transferType" => "INHOUSE",
                    'sessionId'     => $this->account->session_id,
                    'refNo'         => $this->ref_no(),
                    'deviceIdCommon' => $this->account->imei,
                );
            }else{
                $params = array(
                    "benBankCd" => $bankID,
                    "benAccountNumber" => $account_number,
                    "benAccountName" => $name,
                    "destType" => "ACCOUNT",
                    "srcAccountNumber" => $this->account->account_no,
                    "message" => $message,
                    "amount" => $amount,
                    "transferType" => "FAST",
                    'sessionId'     => $this->account->session_id,
                    'refNo'         => $this->ref_no(),
                    'deviceIdCommon' => $this->account->imei,
                );
            }

            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail_web/transfer/verifyMakeTransfer', array(
                'json' => $params,
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault()
            ));
            return json_decode($res->getBody());
        } catch (\Throwable $e) {

        }
        return false;

    }

    public function getAuthList($amount){

        try {

            $params = array(
                "amount" => $amount,
                "serviceCode" => "GCM_FTR_DOM_FAST",
                'sessionId'     => $this->account->session_id,
                'refNo'         => $this->ref_no(),
                'deviceIdCommon' => $this->account->imei,
            );
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail_web/internetbanking/getAuthList', array(
                'json' => $params,
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault()
            ));
            return json_decode($res->getBody());
        } catch (\Throwable $e) {

        }
        return false;
    }
    #sms otp
    public function sendSmsOtp($amount,$deviceID){
        try {

            $params = array(
                "transType" => "TRANSFER",
                "amount" => $amount,
                "authSerialNumber" => $deviceID,
                'sessionId'     => $this->account->session_id,
                'refNo'         => $this->ref_no(),
                'deviceIdCommon' => $this->account->imei,
            );
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail_web/internetbanking/generateSMSOTP', array(
                'json' => $params,
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault()
            ));
            return json_decode($res->getBody());
        } catch (\Throwable $e) {

        }
        return false;
    }
    # end sms otp
    /* soft otp*/
    public function createTranferAuthen($bankID,$account_number,$name,$amount){
        try {
            $type = "GCM_FTR_DOM_FAST";
            if($bankID == "970422"){
                $type = "GCM_FTR_IH_3RD";
            }
            $params = array(
                "transactionAuthen" => [
                    'refNo'         => $this->ref_no(),
                    "custId" => $this->account->cust_id,
                    "sourceAccount" => $this->account->account_no,
                    "destAccount" => $account_number,
                    "amount" => $amount,
                    "transactionType" => $type,
                    "destAccountName" => $name
                ],
                'sessionId'     => $this->account->session_id,
                'refNo'         => $this->ref_no(),
                'deviceIdCommon' => $this->account->imei,
            );
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail_web/vtap/createTransactionAuthen', array(
                'json' => $params,
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault()
            ));
            return json_decode($res->getBody());
        } catch (\Throwable $e) {

        }
        return false;
    }
    /* end soft otp*/
    public function confirmTranfer($bankID,$account_number,$name,$amount,$message,$otp = []){
        if($otp['type'] == "soft"){
            $otp = "ibr|".$otp['deviceID_OTP'] ."||" . $otp['otp'] ."||".time()."|" . $otp['authenID'] . "|" . $otp['refNoAuthen'];
        }else{
            $otp = "ibr|".$otp['deviceID_OTP'] ."|" . $otp['otp'];
        }
        try {
            $type = "FAST";
            if($bankID == "970422"){
                $type = "INHOUSE";
            }
            $params = array(
                "benBankCd" => $bankID,
                "benAccountNumber" => $account_number,
                "benAccountName" => $name,
                "destType" => "ACCOUNT",
                "srcAccountNumber" => $this->account->account_no,
                "message" => $message,
                "amount" => $amount,
                "transferType" => $type,
                "otp" => $otp,
                'sessionId'     => $this->account->session_id,
                'refNo'         => $this->ref_no(),
                'deviceIdCommon' => $this->account->imei,
            );
            $res = $this->client->request('POST', 'https://online.mbbank.com.vn/retail_web/transfer/makeTransfer', array(
                'json' => $params,
                'timeout' => $this->_timeout,
                'headers'     => $this->headerDefault()
            ));
            return json_decode($res->getBody());
        } catch (\Throwable $e) {

        }
        return false;
    }


    private function ref_no()
    {
        return $this->account->username . '-' . date('YmdHms');
    }

    public function generateImei()
    {
        return $this->generateRandomString(8) . '-' . $this->generateRandomString(4) . '-' . $this->generateRandomString(4) . '-' . $this->generateRandomString(4) . '-' . $this->generateRandomString(12);
    }
    private function generateRandomString($length = 20)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    private function headerDefault(){
        return [
            'Host'              => 'online.mbbank.com.vn',
            'Content-Type'      => 'application/json; charset=UTF-8',
            'User-Agent'        => 'MB%20Bank/2 CFNetwork/1331.0.3 Darwin/21.4.0',
            'Connection'        => 'keep-alive',
            'Accept'            => 'application/json',
            'Accept-Language'   => 'vi-VN,vi;q=0.9',
            'Authorization'     => 'Basic QURNSU46QURNSU4=',
            'Accept-Encoding'   => 'gzip, deflate, br'
        ];
    }
}
