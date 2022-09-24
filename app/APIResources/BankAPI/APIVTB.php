<?php

namespace App\APIResources\BankAPI;

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Carbon\Carbon;
use phpseclib\Crypt\RSA;
use App\BankAccount;

class APIVTB
{

    # URL;
    public $url = array(
        'captcha' => 'https://api-ipay.vietinbank.vn/api/get-captcha/',
        'login' => 'https://api-ipay.vietinbank.vn/ipay/wa/signIn',
        'getCustomerDetails' => 'https://api-ipay.vietinbank.vn/ipay/wa/getCustomerDetails',
        'getEntitiesAndAccounts' => 'https://api-ipay.vietinbank.vn/ipay/wa/getEntitiesAndAccounts',
        'getCmsData' => 'https://api-ipay.vietinbank.vn/ipay/wa/getCmsData',
        'getBillPayees' => 'https://api-ipay.vietinbank.vn/ipay/wa/getBillPayees',
        'creditAccountList' => 'https://api-ipay.vietinbank.vn/ipay/wa/creditAccountList',
        'getAvgAccountBal' => 'https://api-ipay.vietinbank.vn/ipay/wa/getAvgAccountBal',
        'getHistTransactions' => 'https://api-ipay.vietinbank.vn/ipay/wa/getHistTransactions',
        'getAccountDetails' => 'https://api-ipay.vietinbank.vn/ipay/wa/getAccountDetails',
        'getCodeMapping' => 'https://api-ipay.vietinbank.vn/ipay/wa/getCodeMapping',
        'napasTransfer' => 'https://api-ipay.vietinbank.vn/ipay/wa/napasTransfer',
        'makeInternalTransfer' => 'https://api-ipay.vietinbank.vn/ipay/wa/makeInternalTransfer',
        'authenSoftOtp' => 'https://api-ipay.vietinbank.vn/ipay/wa/authenSoftOtp'
    );
    # Proxy;
    public $keyProxy = 'TL3Wbv11q2AsH2jZdqygCQ1ytVmR0b3x8GSFjO';

    public $username;
    public $accessCode; // Mật khẩu
    public $captchaCode; // Mã captcha
    public $captchaId; // Id captcha
    public $requestId;
    public $sign;

    # Declare value;
    public $browserInfo = 'Chrome-98.04758109';
    public $lang = 'vi';
    public $clientInfo = '127.0.0.1;Macintosh-10.157';
    public $_timeout = 15;
    public $_keyAnticaptcha = '7b244bc561f0e45b4bf7e38c1a58e31c';
    public $_publicKey = '-----BEGIN PUBLIC KEY-----
    MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDLenQHmHpaqYX4IrRVM8H1uB21
    xWuY+clsvn79pMUYR2KwIEfeHcnZFFshjDs3D2ae4KprjkOFZPYzEWzakg2nOIUV
    WO+Q6RlAU1+1fxgTvEXi4z7yi+n0Zs0puOycrm8i67jsQfHi+HgdMxCaKzHvbECr
    +JWnLxnEl6615hEeMQIDAQAB
    -----END PUBLIC KEY-----';

    # Storage value;
    public $sessionId;
    public $customerNumber;
    public $ipayId;
    public $tokenId;

    public function __construct($username, $accessCode, $customerNumber)
    {
        $this->username = $username;
        $this->accessCode = $accessCode;
        $this->customerNumber = $customerNumber;
        $this->loadData();
    }

    public function loadData()
    {
        //$existed = BankAccount::where('username', $this->username)->where('cardCode', $this->customerNumber)->first();
        $existed = false;
        if ($existed) {
            $this->sessionId = $existed->imei;
            $this->ipayId = $existed->value;
            $this->tokenId = $existed->access_token;
        }
        return true;
    }

    private function headerNull()
    {
        return array(
            'origin' => 'https://ipay.vietinbank.vn',
            'content-type' => 'application/json;charset=UTF-8',
            'accept-language' => 'en-US,en;q=0.9,vi;q=0.8',
            'accept' => 'application/json, text/plain, */*',
            'lang' => 'vi',
            'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36'
        );
    }

    public function getCaptchaImage()
    {
        $this->captchaId = Str::random(9);
        $client = new Client();
        $res = $client->request('GET', $this->url['captcha'].$this->captchaId, [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => null
        ]);
        $svg = $res->getBody()->getContents();
        // $image = SVG::fromString($svg);

        // $doc = $image->getDocument();
        // $doc->getChild(1)->setAttribute('transform', "translate(450,450) scale(44)");
        // $rasterImage = $image->toRasterImage(192, 24, '#FFFFFF');
        // $image1 = Image::make($rasterImage)->encode('png');
        // return base64_encode($image1);
        return $this->bypassCaptcha($svg);
    }

    public function createTask($base64Captcha)
    {
        $params = array(
            'clientKey' => $this->_keyAnticaptcha,
            'task' => array(
                'type' => 'ImageToTextTask',
                'body' => $base64Captcha,
                'phrase' => false,
                'case' => false,
                'numeric' => 0,
                'math' => false,
                'minLength' => 0,
                'maxLength' => 0,
                'websiteURL' => 'api-ipay.vietinbank.vn'
            )
        );
        $client = new Client();
        $res = $client->request('POST', 'https://api.anti-captcha.com/createTask', [
          'timeout' => $this->_timeout,
          'headers' => array(
              'Accept' => 'application/json',
              'Content-Type' => 'application/json',
          ),
          'body' => json_encode($params)
        ]);
        return json_decode($res->getBody());
    }

    public function getCaptchaText($taskId)
    {
        $params = array(
            'clientKey' => $this->_keyAnticaptcha,
            'taskId' => $taskId
        );
        $client = new Client();
        $res = $client->request('POST', 'https://api.anti-captcha.com/getTaskResult', [
          'timeout' => $this->_timeout,
          'headers' => array(
              'Accept' => 'application/json',
              'Content-Type' => 'application/json',
          ),
          'body' => json_encode($params)
        ]);
        return json_decode($res->getBody());
    }

    public function login()
    {
        $isError = true;
        $message = 'Error exception';
        $data = array();
        $captchaByPass = $this->getCaptchaImage();
        $this->captchaCode = $captchaByPass;
        $login = $this->loginAPI();
        if ($login->error == false) {
            $this->sessionId = $login->sessionId;
            $this->tokenId = $login->tokenId;
            $this->ipayId = $login->ipayId;
            $data['sessionId'] = $this->sessionId;
            $data['tokenId'] = $this->tokenId;
            $data['ipayId'] = $this->ipayId;
            $data['addField3'] = $login->addField3;
            $isError = false;
            $message = 'Success';
        } else {
            $message = $login->errorMessage;
        }
        return array(
            'isError' => $isError,
            'message' => $message,
            'data' => $data
        );
    }

    public function waitCaptchaResponse($taskId)
    {
        $loopSuccess = false;
        $count = 0;
        while (true) {
            $catpchaText = $this->getCaptchaText($taskId);
            sleep(2);
            if ($catpchaText->errorId == 0) {
                if ($catpchaText->status == 'ready') {
                    $this->captchaCode = $catpchaText->solution->text;
                    $loopSuccess = true;
                    break;
                }
            }
            $count++;
            if ($count > 20) {
                break;
            }
        }
        return $loopSuccess;
    }

    public function loginAPI()
    {
        $this->requestId = strtoupper(Str::random(12)).'|'.Carbon::now()->valueOf();
        $params = array(
            'accessCode' => $this->accessCode,
            'browserInfo' => $this->browserInfo,
            'captchaCode' => $this->captchaCode,
            'captchaId' => $this->captchaId,
            'clientInfo' => $this->clientInfo,
            'lang' => $this->lang,
            'requestId' => $this->requestId,
            'userName' => $this->username
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['login'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    public function getCustomerDetails()
    {
        $this->requestId = strtoupper(Str::random(12)).'|'.Carbon::now()->valueOf();
        $params = array(
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['getCustomerDetails'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }
    public function getEntitiesAndAccounts()
    {
        $this->requestId = strtoupper(Str::random(12)).'|'.Carbon::now()->valueOf();
        $params = array(
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['getEntitiesAndAccounts'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }
    public function getBillPayees()
    {
        $this->requestId = strtoupper(Str::random(12)).'|'.Carbon::now()->valueOf();
        $params = array(
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['getBillPayees'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }
    public function creditAccountList()
    {
        $this->requestId = strtoupper(Str::random(12)).'|'.Carbon::now()->valueOf();
        $params = array(
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['creditAccountList'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }


    public function getAvgAccountBal()
    {
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'accountNumber' => $this->customerNumber,
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['getAvgAccountBal'], [
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    public function getTransaction($startDate, $endDate)
    {
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'accountNumber' => $this->customerNumber,
            'endDate' => $endDate,
            'lang' => 'vi',
            'maxResult' => '999999999',
            'pageNumber' => 0,
            'requestId' => $this->requestId,
            'searchFromAmt' => '',
            'searchKey' => '',
            'searchToAmt' => '',
            'startDate' => $startDate,
            'tranType' => ''
        );
        try {
            $client = new Client(['http_errors' => false]);
            $res = $client->request('POST', $this->url['getHistTransactions'], [
                'proxy' => $this->proxyVietnam(),
                'timeout' => $this->_timeout,
                'headers' => $this->headerNull(),
                'body' => $this->makeBodyRequestJson($params)
            ]);
            return json_decode($res->getBody());
        } catch (\Exception $e) {
            $params = array(
                'error' => true
            );
            return json_decode(json_encode($params));
        }
    }

    // lấy danh sách ngân hàng để ck
    public function getCodeMapping()
    {
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'context' => "BENEFICIAL_BANK",
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['getCodeMapping'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    //lấy tên tài khoản cùng bank
    public function nameInternalTransfer($beneficiaryAccount)
    {
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'accountNumber' => $beneficiaryAccount,
            'formAction' => "inquiryToAcctDetail",
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['makeInternalTransfer'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    //lấy tên tài khoản khác bank
    public function nameNapasTransfer($beneficiaryAccount, $beneficiaryBin)
    {
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'beneficiaryAccount' => $beneficiaryAccount,
            'beneficiaryBin' => $beneficiaryBin,
            "beneficiaryType" => "account",
            'fromAccount' => $this->customerNumber,
            'formAction' => "validateToAccount",
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['napasTransfer'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    // Tạo ck cùng bank
    public function createInternalTransfer($toAccountNumber,$amount,$message){
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'accountNumber' => $this->customerNumber,
            "accountType" => $this->accountType,
            'formAction' => "validateTransaction",
            'lang' => $this->lang,
            "amount" => $amount,
            "reference" => $message,
            "fromBranchId" => $this->bsb,
            "currency" => $this->currencyCode,
            "toAccountNumber" =>  $toAccountNumber,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['makeInternalTransfer'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    // tạo yêu cầu chuyển khoản ngoài
    public function createNapasTransfer($amount,$message){
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            "amount" => $amount,
            'fromAccount' => $this->customerNumber,
            'formAction' => "validateTransaction",
            'lang' => $this->lang,
            'reference' => $message,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['napasTransfer'], [
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    // tạo yêu cầu chuyển khoản ngoài
    public function step1NapasTransfer($amount, $message)
    {
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            "amount" => $amount,
            'fromAccount' => $this->customerNumber,
            'formAction' => "validateTransaction",
            'lang' => $this->lang,
            'reference' => $message,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['napasTransfer'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    // tạo yêu cầu gửi otp chuyển khoản ngoài
    public function step2NapasTransfer()
    {
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'fromAccount' => $this->customerNumber,
            'formAction' => "sendSmsOtp",
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['napasTransfer'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    // confirm otp chuyển khoản ngoài
    public function step3NapasTransfer($authenticationActionCode, $otpValue)
    {
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'fromAccount' => $this->customerNumber,
            'formAction' => "confirmTransaction",
            'otpType' => "SMS",
            'authenticationActionCode' => $authenticationActionCode,
            'otpValue' => $otpValue,
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['napasTransfer'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    // lấy kq chuyển khoản ngoài
    public function step4NapasTransfer()
    {
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['getPayees'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    // lấy kq chuyển khoản ngoài
    public function getDetailSoftTransfer($paymentId){
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );$params = array(
            'username' => $this->username,
            'formAction' => "inqTran",
            'lang' => $this->lang,
            'paymentId' => $paymentId,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['authenSoftOtp'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    // softOTP
    public function confirmSoftTransfer($authenticationActionCode){
        $this->requestId = strtoupper(Str::random(12)) . '|' . Carbon::now()->valueOf();
        $params = array(
            'fromAccount' => $this->customerNumber,
            'formAction' => "confirmTransaction",
            'otpType' => "SOFT_OTP",
            'authenticationActionCode' => $authenticationActionCode,
            'otpValue' => "",
            'lang' => $this->lang,
            'requestId' => $this->requestId
        );
        $client = new Client(['http_errors' => false]);
        $res = $client->request('POST', $this->url['napasTransfer'], [
            'proxy' => $this->proxyVietnam(),
            'timeout' => $this->_timeout,
            'headers' => $this->headerNull(),
            'body' => $this->makeBodyRequestJson($params)
        ]);
        return json_decode($res->getBody());
    }

    public function makeBodyRequestJson($params)
    {
        if ($this->sessionId) {
            $params['sessionId'] = $this->sessionId;
        }
        ksort($params);
        $this->sign = md5(http_build_query($params));
        $params['signature'] = $this->sign;

        ksort($params);
        $rsa = new RSA();
        $rsa->loadKey($this->_publicKey);
        $data = base64_encode($rsa->encrypt(json_encode($params)));
        return json_encode(array(
            'encrypted' => $data
        ));
    }

    private function bypassCaptcha($svg)
    {
        $model = [
         "MCLCLCLCLCLCCLCLCLCLCLCLCCLCLCLCLCCLCLCLCLCCZMCCLCLCCLCLCCLCLCCLCZ" => 0,
         "MLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLZ" => 1,
         "MLLLLLLLLLLLLCLCLCCLCLCLLLLLLLLLLLLLLLLLLLLLLLLLLLLLZ" => 2,
         "MCLCCLCLCCLCLCLLLLLLLLLLLCCCCCLLLLLLLLLLLLCCCCCCCCLLLLLCCCLCCLCCLCLCCZ" => 3,
         "MLLLLLLLLLLLLLLLLLCLCLCCLCLCLLLLLLLLLLLLLLLLLLLLLLLLLZMLLLLLLLLLLLLLLLZMLLLZ" => 4,
         "MCLCLCLCLCLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLCCLLLLLLLCLCLCZ" => 5,
         "MCLCLCLCLCCCLCLCLCLCLCLCLCLCLCLCLCLCLCLCLCLCCLCLCZMLCCCCLCLCCLCZ" => 6,
         "MLCLCCLCLCLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLCZ" => 7,
         "MCLCLCLCCLCCLCLCLCLCCLCLCLCLCCLCCLCLCLCCLCLCCLCLCZMLCLCCCCLCZMLCCLCLCCCCLCZ" => 8,
         "MLCLCLCLCLCLCCLCLCLCLCCLCLCLCLCCCCCLCLCLCLCLCLCLCLCZMLCCCCCLCLCCCCLCZ" => 9
        ];
        $chars = array();
        preg_match_all('#<path fill="(.*?)" d="(.*?)"/>#', $svg, $matches);
        if (sizeof($matches) != 3) {
            return;
        }
        
        $paths = $matches[2];
        foreach ($paths as $path) {
            if (preg_match("#M([0-9]+)#", $path, $p)) {
                $pattern = preg_replace("#[0-9 \.]#", "", $path);
                $chars[$p[1]] = $model[$pattern];
            }
        }
        ksort($chars);
        return implode("", $chars);
    }

    public function proxyVietnam()
    {
        return 'http://ProxyVN2022805:eBsUuyYb@103.180.153.140:5022';
    }
}
