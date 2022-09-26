<?php

namespace App\APIResources\BankAPI;

use GuzzleHttp\Client;
use Illuminate\Support\Str;

class Vietcombank
{
    protected $url = [
        "getCaptcha" => "https://digiapp.vietcombank.com.vn/utility-service/v1/captcha/",
        "login" => "https://digiapp.vietcombank.com.vn/authen-service/v1/login",
        "getHistories" => "https://digiapp.vietcombank.com.vn/bank-service/v1/transaction-history",
        "tranferOut" => "https://digiapp.vietcombank.com.vn/napas-service/v1/init-fast-transfer-via-accountno",
        "genOtpOut" => "https://digiapp.vietcombank.com.vn/napas-service/v1/transfer-gen-otp",
        "confirmTranferOut" => "https://digiapp.vietcombank.com.vn/napas-service/v1/transfer-confirm-otp",
        "tranferIn" => "https://digiapp.vietcombank.com.vn/transfer-service/v1/init-internal-transfer",
        "genOtpIn" => "https://digiapp.vietcombank.com.vn/transfer-service/v1/transfer-gen-otp",
        "confirmTranferIn" => "https://digiapp.vietcombank.com.vn/transfer-service/v1/transfer-confirm-otp",
        "getBanks" => "https://digiapp.vietcombank.com.vn/utility-service/v1/get-banks",
        "getAccountDeltail" => "https://digiapp.vietcombank.com.vn/bank-service/v1/get-account-detail",
        "getlistAccount" => "https://digiapp.vietcombank.com.vn/bank-service/v1/get-list-account-via-cif"
    ];
    protected $captchaMode = 0;
    protected $captchaApiKeys = [
        ""
    ];
    protected $lang = 'vi';
    protected $_timeout = 15;
    protected $DT = "Windows";
    protected $OV = "10";
    protected $PM = "Chrome 104.0.0.0";
    protected $checkAcctPkg = "1";
    protected $username;
    protected $password;
    protected $account_number;
    protected $captchaToken;
    #account
    protected $sessionId;
    protected $mobileId;
    protected $clientId;
    protected $cif;

    public function __construct($username,$password,$account_number)
    {
        $this->username = $username;
        $this->password = $password;
        $this->account_number = $account_number;
    }

    public function getCaptcha(){
        $this->captchaToken = Str::random(30);
        $url = "https://digiapp.vietcombank.com.vn/utility-service/v1/captcha/".$this->captchaToken;
        $client = new Client(['http_errors' => false]);
        $res = $client->request('GET', $url, [
            'timeout' => $this->_timeout,
            'headers' => array(
                'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36'
            ),
        ]);
        $result = $res->getBody()->getContents();
        return base64_encode($result);
    }
    public function solveCaptcha(){
        $getCaptcha = $this->getCaptcha();
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', "https://api.tungduy.com/api/captcha/vietcombank", [
            'timeout' => $this->_timeout,
            "body" => json_encode(["apikey" => $this->captchaApiKeys[$this->captchaMode],"base64" => $getCaptcha]),
            'headers' => array(
                'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36',
                'Content-Type' => 'application/json'
            ),
        ]);
        $result = json_decode($res->getBody()->getContents());
        if ($result->status !== true) {
            return ["status" => false,"msg" =>"Solve Captcha failed: ".$result->message];
        } else {                $this->captchaValue = $result->captcha;
            return ["status" => true,"key" => $this->captchaToken,"captcha" => $this->captchaValue];
        }
    }
    public function doLogin(){
        $solveCaptcha = $this->solveCaptcha();
        if($solveCaptcha['status'] == false){
            return $solveCaptcha;
        }
        $param = array(
            "DT" => $this->DT,
            "OV" => $this->OV,
            "PM" => $this->PM,
            "captchaToken" => $this->captchaToken,
            "captchaValue" => $this->captchaValue,
            "checkAcctPkg" => $this->checkAcctPkg,
            "lang" => $this->lang,
            "mid" => 6,
            "password" => $this->password,
            "user" => $this->username
        );
        $result = $this->curlPost($this->url['login'],$param);
        if($result->code == 00 ){
            $this->sessionId = $result->sessionId;
            $this->mobileId = $result->userInfo->mobileId;
            $this->clientId = $result->userInfo->clientId;
            $this->cif = $result->userInfo->cif;
            return array(
                'success' => true,
                'message' => "success",
                'data' => $result ? : ""
            );
        }else{
            return array(
                'success' => false,
                'message' => $result->des,
                "param" => $param,
                'data' => $result ? : ""
            );
        }
    }
    public function setData($sessionId,$mobileId,$clientId,$cif){
        $this->sessionId = $sessionId;
        $this->mobileId = $mobileId;
        $this->clientId = $clientId;
        $this->cif = $cif;
        return $this;
    }
    public function getlistAccount(){
        $param = array(
            "DT" => $this->DT,
            "OV" => $this->OV,
            "PM" => $this->PM,
            "mid" => 8,
            "cif" => $this->cif,
            "user" => $this->username,
            "mobileId" => $this->mobileId,
            "clientId" => $this->clientId,
            "sessionId" => $this->sessionId
        );
        $result = $this->curlPost($this->url['getlistAccount'],$param);
        return $result;

    }
    public function getAccountDeltail(){
        $param = array(
            "DT" => $this->DT,
            "OV" => $this->OV,
            "PM" => $this->PM,
            "accountNo" => $this->account_number,
            "accountType" => "D",
            "mid" => 13,
            "cif" => $this->cif,
            "user" => $this->username,
            "mobileId" => $this->mobileId,
            "clientId" => $this->clientId,
            "sessionId" => $this->sessionId
        );
        $result = $this->curlPost($this->url['getAccountDeltail'],$param);
        return $result;

    }
    public function getHistories($fromDate = "24/07/2022",$toDate = "23/08/2022"){
        $param = array(
            "DT" => $this->DT,
            "OV" => $this->OV,
            "PM" => $this->PM,
            "accountNo" => $this->account_number,
            "accountType" => "D",
            "fromDate" => $fromDate,
            "toDate" => $toDate,
            "lang" => $this->lang,
            "pageIndex" => 0,
            "lengthInPage" => 999999,
            "stmtDate" => "",
            "stmtType" => "",
            "mid" => 14,
            "cif" => $this->cif,
            "user" => $this->username,
            "mobileId" => $this->mobileId,
            "clientId" => $this->clientId,
            "sessionId" => $this->sessionId
        );
        $result = $this->curlPost($this->url['getHistories'],$param);
        return $result;
    }
    public function getBanks(){
        $param = array(
            "DT" => $this->DT,
            "OV" => $this->OV,
            "PM" => $this->PM,
            "lang" => $this->lang,
            "fastTransfer" => "1",
            "mid" => 23,
            "cif" => $this->cif,
            "user" => $this->username,
            "mobileId" => $this->mobileId,
            "clientId" => $this->clientId,
            "sessionId" => $this->sessionId
        );
        $result = $this->curlPost($this->url['getBanks'],$param);
        return $result;
    }
    public function createTranferOutVietCombank($bankCode,$account_number,$amount,$message){
        $param = array(
            "DT" => $this->DT,
            "OV" => $this->OV,
            "PM" => $this->PM,
            "lang" => $this->lang,
            "debitAccountNo" => $this->account_number,
            "creditAccountNo" => $account_number,
            "creditBankCode" => $bankCode,
            "amount" => $amount,
            "feeType" => 1,
            "content" => $message,
            "ccyType" => "1",
            "mid" => 62,
            "cif" => $this->cif,
            "user" => $this->username,
            "mobileId" => $this->mobileId,
            "clientId" => $this->clientId,
            "sessionId" => $this->sessionId
        );
        $result = $this->curlPost($this->url['tranferOut'],$param);
        return $result;
    }
    public function createTranferInVietCombank($account_number,$amount,$message){
        $param = array(
            "DT" => $this->DT,
            "OV" => $this->OV,
            "PM" => $this->PM,
            "lang" => $this->lang,
            "debitAccountNo" => $this->account_number,
            "creditAccountNo" => $account_number,
            "amount" => $amount,
            "activeTouch" => 0,
            "feeType" => 1,
            "content" => $message,
            "ccyType" => "",
            "mid" => 16,
            "cif" => $this->cif,
            "user" => $this->username,
            "mobileId" => $this->mobileId,
            "clientId" => $this->clientId,
            "sessionId" => $this->sessionId
        );
        $result = $this->curlPost($this->url['tranferIn'],$param);
        return $result;
    }
    public function genOtpTranFer($tranId, $type = "OUT"){
        $param = array(
            "DT" => $this->DT,
            "OV" => $this->OV,
            "PM" => $this->PM,
            "lang" => $this->lang,
            "tranId" => $tranId,
            "type" => 5, // 1 là SMS,5 là smart otp
            "mid" => 17,
            "cif" => $this->cif,
            "user" => $this->username,
            "mobileId" => $this->mobileId,
            "clientId" => $this->clientId,
            "sessionId" => $this->sessionId
        );
        if($type == "IN"){
            $result = $this->curlPost($this->url['genOtpIn'],$param);
        }else{
            $result = $this->curlPost($this->url['genOtpOut'],$param);
        }
        return $result;
    }
    public function confirmTranfer($tranId,$challenge,$otp , $type = "OUT"){
        $param = array(
            "DT" => $this->DT,
            "OV" => $this->OV,
            "PM" => $this->PM,
            "lang" => $this->lang,
            "tranId" => $tranId,
            "otp" => $otp,
            "challenge" => $challenge,
            "mid" => 18,
            "cif" => $this->cif,
            "user" => $this->username,
            "mobileId" => $this->mobileId,
            "clientId" => $this->clientId,
            "sessionId" => $this->sessionId
        );
        if($type == "IN"){
            $result = $this->curlPost($this->url['confirmTranferIn'],$param);
        }else{
            $result = $this->curlPost($this->url['confirmTranferOut'],$param);
        }
        return $result;
    }
    private function curlPost($url = "",$data = array()){
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $url, [
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => json_encode($data),
        ]);
        $result = $res->getBody()->getContents();
        return json_decode($result);
    }
    private function headerNull()
    {
        return array(
            'Accept' =>  'application/json',
            'Accept-Encoding' =>   'gzip, deflate, br',
            'Accept-Language' =>    'vi',
            'Connection' =>    'keep-alive',
            'Content-Type' =>    'application/json',
            'Host' =>    'digiapp.vietcombank.com.vn',
            'Origin' =>    'https://vcbdigibank.vietcombank.com.vn',
            'Referer' =>    'https://vcbdigibank.vietcombank.com.vn/',
            'sec-ch-ua-mobile' =>    '?0',
            'Sec-Fetch-Dest' =>    'empty',
            'Sec-Fetch-Mode' =>    'cors',
            'Sec-Fetch-Site' =>    'same-site',
            'User-Agent' =>    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36',
            'X-Channel' =>    'Web',
        );
    }
}
