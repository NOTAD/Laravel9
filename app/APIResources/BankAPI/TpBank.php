<?php
namespace App\Bank;

use GuzzleHttp\Client;
use LaravelAnticaptcha\Anticaptcha\ImageToText;

class  TpBank
{
    protected $username;
    protected $password;
    protected $APP_VERSION = "10.10.81";
    protected $SOURCE_APP = "HYDRO";
    protected $PLATFORM_VERSION = "15.1.1";
    protected $DEVICE_NAME = "";
    protected $imei = "";
    protected $host = "https://ebank.tpb.vn/";
    protected $token = "";
    protected $debtorAccountNumber = "";
    protected $captchaToken = "";
    protected $imageCaptcha = "";
    protected $captchaValue = "";
    protected $proxy = "";
    protected $_timeout = 15;
    public function __construct()
    {

    }

    public function setData($imei,$DEVICE_NAME,$proxy){
        $this->DEVICE_NAME = $DEVICE_NAME;
        $this->imei = $imei;
        $this->proxy = $proxy;
    }

    public function setToken($token,$debtorAccountNumber){
        $this->token = $token;
        $this->debtorAccountNumber = $debtorAccountNumber;
    }
    public function solveCaptcha(){
        $this->getCaptcha();
        $api = new ImageToText();
        $api->setKey("");
        $api->setFile(storage_path("captcha/tpbank/".$this->captchaToken.".png"));
        if (!$api->createTask()) {
            unlink(storage_path("captcha/tpbank/".$this->captchaToken.".png"));
            return ["status" => false,"msg" => "API createTask send failed - ".$api->getErrorMessage()];
        }
        $taskId = $api->getTaskId();
        if (!$api->waitForResult()) {
            unlink(storage_path("captcha/tpbank/".$this->captchaToken.".png"));
            return ["status" => false,"msg" =>"API getTaskSolution send failed - ".$api->getErrorMessage()];
        } else {
            $this->captchaValue = $api->getTaskSolution();
            return ["status" => true,"key" => $this->captchaToken,"captcha" => $this->captchaValue];
        }
    }
    public function getCaptcha(){
        $p = array(
            "captcha" => [
                "clientIP" => "10.10.10.10",
                "clientId" => $this->imei
            ],
            "theme" => "light"
        );
        $getCaptcha = $this->curlPost($this->host."gateway/api/common-presentation-service/v1/captcha/generate",$p);
        $getCaptcha = json_decode($getCaptcha,true);
        $this->captchaToken = $getCaptcha['captcha']['id'];
        $this->imageCaptcha = $getCaptcha['captcha']['captchaImage'];
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $this->imageCaptcha));
        file_put_contents(storage_path("captcha/tpbank/".$this->captchaToken.".png"),$data);
        return $getCaptcha;
    }
    public function doLogin($username, $password){
        $params = array(
            "username" => $username,
            "password" => $password,
            "deviceId" => $this->imei,
            "step_2FA" => "VERIFY",
            "captcha" => [
                "clientIP" => "10.10.10.10",
                "id" => $this->captchaToken,
                "clientId" => $this->imei,
                "captcha" => $this->captchaValue,
                "captchaImage" => $this->imageCaptcha
            ]
        );
        $doLogin = $this->curlPost($this->host."gateway/api/auth/login",$params);
        $doLogin = json_decode($doLogin,true);
        return $doLogin;
    }

    public function getInfo(){
        $params = array();
        $getInfo = $this->curlPost($this->host."gateway/api/customers-presentation-service/v1/customers",$params);
        $getInfo = json_decode($getInfo,true);
        return $getInfo;
    }
    public function getListBank(){
        $params = array();
        $getListBank = $this->curlPost($this->host."gateway/api/common-presentation-service/v1/banknapas",$params);
        $getListBank = json_decode($getListBank,true);
        return $getListBank;
    }
    public function getHistories($fromDate,$toDate){
        $params = array("toDate" => $toDate,"fromDate" => $fromDate,"currency" => "VND","accountNo" => $this->debtorAccountNumber);
        $getHistories = $this->curlPost($this->host."gateway/api/smart-search-presentation-service/v1/account-transactions/find",$params);
        $getHistories = json_decode($getHistories,true);
        return $getHistories;
    }
    public function getNameFromAccountnumber($creditorAccountNumber,$creditorBankId){
        if($creditorBankId == 970423 || $creditorBankId == "970423"){
            $params = array("creditorAccountNumber" => $creditorAccountNumber);
            $getNameFromAccountnumber = $this->curlPost($this->host."gateway/api/fund-transfer-presentation-service/v1/creditor-info/internal",$params);
            $getNameFromAccountnumber = json_decode($getNameFromAccountnumber,true);
            return $getNameFromAccountnumber;
        }
        $params = array("creditorAccountNumber" => $creditorAccountNumber,"transferMethod" => "","creditorBankId" => $creditorBankId,"debtorAccountNumber" => $this->debtorAccountNumber);
        $getNameFromAccountnumber = $this->curlPost($this->host."gateway/api/fund-transfer-presentation-service/v2/creditor-info/external/account-number",$params);
        $getNameFromAccountnumber = json_decode($getNameFromAccountnumber,true);
        return $getNameFromAccountnumber;
    }
    public function createTranferOutTPBank($creditorAccountNumber,$creditorBankId,$amount = 10000,$messages,$creditorInfo){
        if($creditorBankId == 970423 || $creditorBankId == "970423"){
            $params = array(
                "creditorAccountNumber" => $creditorAccountNumber,
                "debtorAccountNumber" => $this->debtorAccountNumber,
                "remark" => $messages,
                "type" => 0,
                "paymentType" => "VN_INT_TRANS_ACC",
                "amount" => $amount,
                "currency"=> $creditorInfo['currency'] ,
                "transferMode"=> "SINGLE",
                "creditorName" => $creditorInfo['name']
            );
            $createTranferOutTPBank = $this->curlPost($this->host."gateway/api/fund-transfer-presentation-service/v1/fund-transfer/account-number/verify",$params);
            $createTranferOutTPBank = json_decode($createTranferOutTPBank,true);
            return $createTranferOutTPBank;
        }
        $params = array(
            "creditorAccountNumber" => $creditorAccountNumber,
            "creditorBankId" => $creditorBankId,
            "debtorAccountNumber" => $this->debtorAccountNumber,
            "remark" => $messages,
            "type" => 1,
            "creditorBankNameEn" => $creditorInfo['extBankNameEn'],
            "paymentType" => "VN_INTBA_TRANS_ACC",
            "amount" => $amount,
            "currency"=> $creditorInfo['currency'] ,
            "transferMode"=> "SINGLE",
            "creditorBankNameVn" => $creditorInfo['extBankNameVn'],
            "creditorName" => $creditorInfo['name']
        );
        $createTranferOutTPBank = $this->curlPost($this->host."gateway/api/fund-transfer-presentation-service/v2/fund-transfer/napas-account-number/verify",$params);
        $createTranferOutTPBank = json_decode($createTranferOutTPBank,true);
        return $createTranferOutTPBank;
    }

    public function confirmTranfer($id,$code){
        $params = array(
            "transferMode" => "SINGLE",
            "saveTemplate" => false,
            "authCode" => $code,
            "id" => $id,
            "authMethod" => "ETOKEN"
        );
        $confirmTranfer = $this->curlPost($this->host."gateway/api/fund-transfer-presentation-service/v2/fund-transfer/napas-account-number/confirm",$params);
        $confirmTranfer = json_decode($confirmTranfer,true);
        return $confirmTranfer;
    }


    public function randString($length)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $size = strlen($chars);
        $str = "";
        for ($i = 0;$i < $length;$i++)
        {
            $str .= $chars[rand(0, $size - 1) ];
        }
        return $str;
    }

    public function get_microtime()
    {
        return floor(microtime(true) * 1000);
    }
    public function get_imei()
    {
        $time = md5($this->get_microtime());
        $text = substr($time, 0, 8) . "-";
        $text .= substr($time, 8, 4) . "-";
        $text .= substr($time, 12, 4) . "-";
        $text .= substr($time, 16, 4) . "-";
        $text .= substr($time, 17, 12);
        $text = strtoupper($text);
        return $text;
    }
    public function writeLog($file_name, $message)
    {
        $body = "\n";
        $body .= date("d/m/Y - H:i:s"). "\n";
        $body .= $message . "\n";
        $body .= '-----------------';
        $file = $file_name;
        $fp = fopen($file, 'a+');
        fwrite($fp, $body);
        fclose($fp);
        return true;
    }
    public function curlPost($url = null, $param = [])
    {
        $header = [
            'Content-Type'      => 'application/json',
            "Connection" => "keep-alive",
            "Accept-Language" => "vi-VN;q=1.0, en-VN;q=0.9",
            "DEVICE_ID" => $this->imei,
            "APP_VERSION" => $this->APP_VERSION,
            "SOURCE_APP" => $this->SOURCE_APP,
            "PLATFORM_NAME: IOS",
            "PLATFORM_VERSION" => $this->PLATFORM_VERSION,
            "DEVICE_NAME" => $this->DEVICE_NAME,
            "User-Agent" => "Hydrobank/10.10.81 (com.fpt.tpb.emobile; build:6; iOS 15.1.1) Alamofire/4.8.1"
        ];
        if ($this->token)
        {
            $header['Authorization'] = "Bearer " . trim($this->token);
        }
        if($this->proxy){
            $client = new Client(['http_errors' => false,'proxy' => $this->proxy]);
        }else{
            $client = new Client(['http_errors' => false]);
        }

        $res = $client->request('POST', $url, array(
            'json' => $param,
            'timeout' => $this->_timeout,
            'headers'     => $header,
        ));
        //$data = json_decode($res->getBody());
        return $res->getBody()->getContents();
    }
}
