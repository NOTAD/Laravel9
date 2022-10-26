<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Azcaptcha;
use App\Models\BankSessions;

class VietcombankAPI {
    private $config, $captcha_service, $currentUser, $captcha_timeout, $username;
    private $sessionId, $cif, $mobileId, $user, $clientId;
    private $using_session;
    /**
     * @var mixed
     */
    private $private_key, $public_key;

    public function __construct() {
        $this->config          = [
            'get_captcha_url'       => 'https://digiapp.vietcombank.com.vn/utility-service/v1/captcha/',
            'valid_captcha_url'     => 'https://digiapp.vietcombank.com.vn/utility-service/v1/verify-captcha/',
            'login_url'             => 'https://digiapp.vietcombank.com.vn/authen-service/v1/login',
            'get_list_accounts_url' => 'https://digiapp.vietcombank.com.vn/bank-service/v1/get-list-ddaccount',
            'init_transfer_247_url' => 'https://digiapp.vietcombank.com.vn/napas-service/v1/init-fast-transfer-via-accountno',
            'init_transfer_url'     => 'https://digiapp.vietcombank.com.vn/transfer-service/v1/init-internal-transfer',
            'gen_otp_url'           => 'https://digiapp.vietcombank.com.vn/napas-service/v1/transfer-gen-otp',
            'confirm_otp_url'       => 'https://digiapp.vietcombank.com.vn/napas-service/v1/transfer-confirm-otp',
            'get_history_url'       => 'https://digiapp.vietcombank.com.vn/bank-service/v1/transaction-history',
            'decrypt_url'           => 'http://localhost:3000'
        ];
        $this->captcha_timeout = 50;
        $this->captcha_service = new Azcaptcha( [
            'defaultTimeout' => $this->captcha_timeout,
            'apiKey'         => {captcha_api_key}
        ] );
        $key_pairs = $this->get_client_key();
        $this->public_key = $key_pairs['public'];
        $this->private_key = $key_pairs['private'];
    }


    private function solve_captcha() {
        # Get new captcha image
        $random_captcha = vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( random_bytes( 16 ) ), 4 ) );
        $post_url       = $this->config['get_captcha_url'] . $random_captcha;
        $req_headers    = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36',
            'Referer'    => 'https://vcbdigibank.vietcombank.com.vn/login',
            'Host'       => 'vcbdigibank.vietcombank.com.vn',
            'Accept'     => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'
        ];
        $resp           = Http::withHeaders( $req_headers )
                              ->get( $post_url )->body();

        $temp_file = tempnam( sys_get_temp_dir(), $random_captcha );
        file_put_contents( $temp_file, $resp );
        try {
            $captcha_result = $this->captcha_service->normal( $temp_file );
        } catch (\Exception $e) {
            return [
                'desc' => 'Captcha exception error '.json_encode($e),
                'error' => 'critical'
            ];
        }

        return [
            'result'     => $captcha_result,
            'captcha_id' => $random_captcha
        ];
    }

    private function get_client_key(){
        $request_url = $this->config['decrypt_url'].'/vcb_get_key';
        $key_resp = Http::get($request_url);
        return $key_resp->json();
    }
    private function get_rsa_encrypted($query){
        $query["DT"]   = "Windows";
        $query["OV"]   = 10;
        $query["PM"]   = "Chrome 104.0.0.0";
        $query["lang"] = "vi";
        if ( isset( $this->sessionId ) ) {
            $query["sessionId"] = $this->sessionId;
        }
        if ( isset( $this->cif ) ) {
            $query["cif"] = $this->cif;
        }
        if ( isset( $this->clientId ) ) {
            $query["clientId"] = $this->clientId;
        }
        if ( isset( $this->user ) ) {
            $query["user"] = $this->user;
        }
        if ( isset( $this->mobileId ) ) {
            $query["mobileId"] = $this->mobileId;
        }
        $raw_data = [
            'key'=> $this->public_key,
            'data'=> $query
        ];
        $request_data = [
            'data'=> $raw_data
        ];
        $post_url = $this->config['decrypt_url']."/vcb_encrypt";
        $resp = Http::post($post_url, $request_data);
        return $resp->json();
    }
    private function get_rsa_decrypted($encrypted_param){
        $post_url = $this->config['decrypt_url']."/vcb_decrypt";
        $post_data = [
            'data'=> $encrypted_param,
            'key'=> $this->private_key,
        ];
        $resp = Http::post($post_url, $post_data);
        return (object) $resp->json();
    }
    private function vcb_request( $url, $query ) {
        $current_time = Carbon::now()->valueOf();
        //16612817523731
        $headers = [
            'Content-Type'    => 'application/json',
            'Accept-Language' => 'vi',
            'Connection'      => 'keep-alive',
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13',
            'Origin'          => 'https://vcbdigibank.vietcombank.com.vn',
            'Sec-Fetch-Site'  => 'same-origin',
            'Sec-Fetch-Mode'  => 'cors',
            'Sec-Fetch-Dest'  => 'empty',
            'Referer'         => 'https://vcbdigibank.vietcombank.com.vn/',
            'Accept'          => 'application/json',
            'Authorization'   => 'Bearer null',
            'X-Channel'       => 'Web',
            'X-Request-ID'    => $current_time
        ];


        $response = Http::withHeaders( $headers )
                        ->post( $url, $query );

        return $response->body();
    }

    private function login_by_session() {
        $userInfo        = $this->currentUser;
        $this->sessionId = $userInfo->sessionId;
        $this->cif       = $userInfo->userInfo['cif'];
        $this->mobileId  = $userInfo->userInfo['mobileId'];
        $this->user      = $this->username;
        $this->clientId  = $userInfo->userInfo['clientId'];
        $test_status     = $this->getListAccount();
        if ( $test_status['status'] === 'success' ) {
            $this->using_session = true;
            return true;
        }
        $this->using_session = false;
        return false;
    }

    private function getListAccount() {
        $data         = [
            "mid"         => 35,
            "serviceCode" => "0551",
            "type"        => "1",
        ];
        $encrypted_data   = $this->get_rsa_encrypted( $data );
        $query_resp_encrypted   = $this->vcb_request( $this->config['get_list_accounts_url'], $encrypted_data );
        $query_resp = $this->get_rsa_decrypted( $query_resp_encrypted );
        if ( isset( $query_resp->code ) ) {
            if ( $query_resp->code === "00" ) {
                if ( $query_resp->code === 'EXP' || $query_resp->code === 'KICKOUT' || $query_resp->code === '108' ) {
                    //Session expired
                    return [
                        'status' => 'failed',
                        'code'   => 'USER_NEED_LOGIN',
                        'data'   => $query_resp
                    ];
                } else {
                    return [
                        'status' => 'success',
                        'code'   => 'USER_LOGGED_IN',
                        'data'   => $query_resp
                    ];
                }
            } else {
                if ( $query_resp->code === 'EXP' || $query_resp->code === 'KICKOUT' || $query_resp->code === '108' ) {
                    //Session expired
                    return [
                        'status' => 'failed',
                        'code'   => 'USER_NEED_LOGIN',
                        'data'   => $query_resp
                    ];
                }
            }
        }

        return [
            'status' => 'failed',
            'code'   => 'USER_NEED_LOGIN',
            'data'   => $query_resp
        ];
    }

    private function login( $username, $password = null ) {
        # Check if user already logged in
        $query = BankSessions::query();
        $query = $query->where( 'username', $username );
        $query->where( 'bank', 'VCB' );
        $user           = $query->first();
        $this->username = $username;
        if ( $user ) {
            $this->currentUser = $user;
            $login_result      = $this->login_by_session();
            if ( ! $login_result ) {
                //Delete old session
                $user->delete();

                return $this->login( $username, $password );
            } else {
                return [
                    'status'  => 'success',
                    'code'    => 'SUCCESS',
                    'desc'    => 'Login success',
                    'details' => $user
                ];
            }

        } else {
            $end_time    = Carbon::now()->addSeconds( $this->captcha_timeout );
            $try_counter = 0;
            while ( $try_counter < 5 ) {
                $current_time = Carbon::now();
                $timeout      = $current_time->gte( $end_time );
                if ( $timeout ) {
                    return [
                        'status'  => 'failed',
                        'code'    => 'CAPTCHA_ERROR',
                        'desc'    => 'Captcha Time out',
                        'details' => 'Maximum time out ' . $this->captcha_timeout . ' exception'
                    ];
                }
                $captcha_result = $this->solve_captcha();
                if ( isset( $captcha_result['error'] ) ) {
                    //Captcha has some error
                    if ( $captcha_result['error'] == 'critical' ) {
                        return [
                            'status'  => 'failed',
                            'code'    => 'CAPTCHA_ERROR',
                            'desc'    => 'Captcha service error!',
                            'details' => $captcha_result['desc']
                        ];
                    }
                }
                //Captcha solved success
                if ( isset( $captcha_result['result'] ) ) {
                    //Captcha solved success
                    break;
                }
                $try_counter ++;
                sleep( 1 );
            }
            $captcha_id    = $captcha_result['captcha_id'];
            $captcha_value = $captcha_result['result'];

            $message_data = array(
                "captchaToken" => $captcha_id,
                "captchaValue" => $captcha_value->code,
                "checkAcctPkg" => 1,
                "mid"          => 6,
                "password"     => $password,
                "user"         => $username,
            );
            $encrypted_data   = $this->get_rsa_encrypted( $message_data );
            $query_resp_encrypted   = $this->vcb_request( $this->config['login_url'], $encrypted_data );
            $query_resp = $this->get_rsa_decrypted( $query_resp_encrypted );
            if ( isset( $query_resp->code ) ) {
                //NO problem with login
                if ( isset( $query_resp->code ) ) {
                    //Decrypt success
                    switch ( $query_resp->code ) {
                        case '00':
                            //Login success
                            //Add user to DB
                            $new_session            = new BankSessions();
                            $new_session->username  = $username;
                            $new_session->bank      = 'VCB';
                            $new_session->sessionId = $query_resp->sessionId;
                            $new_session->userInfo  = $query_resp->userInfo;
                            $new_session->save();
                            $this->currentUser = $new_session;
                            $this->sessionId   = $query_resp->sessionId;
                            $this->cif         = $query_resp->userInfo['cif'];
                            $this->mobileId    = $query_resp->userInfo['mobileId'];
                            $this->user        = $this->username;
                            $this->clientId    = $query_resp->userInfo['clientId'];

                            return [
                                'status'  => 'success',
                                'code'    => 'SUCCESS',
                                'desc'    => 'Login success',
                                'details' => $new_session
                            ];
                        case '3005':
                            //Wrong username
                            return [
                                'status'  => 'failed',
                                'code'    => 'WRONG_USERNAME',
                                'desc'    => 'Login error',
                                'details' => 'Sai tên đăng nhập'
                            ];
                        case '16':
                            //Wrong password
                            return [
                                'status'  => 'failed',
                                'code'    => 'WRONG_PASSWORD',
                                'desc'    => 'Login error',
                                'details' => 'Sai mật khẩu'
                            ];
                        case '116':
                            //Old account
                            return [
                                'status'  => 'failed',
                                'code'    => 'OLD_ACCOUNT',
                                'desc'    => 'Login error',
                                'details' => 'Tài khoản cũ chưa chuyển đổi sang hệ thống mới'
                            ];
                        case '019':
                            //Not registered IB account
                            return [
                                'status'  => 'failed',
                                'code'    => 'ACCOUNT_NOT_REGISTERED',
                                'desc'    => 'Login error',
                                'details' => 'Tài khoản chưa đăng ký Internet banking'
                            ];
                        case '42':
                            //NEED CMND to login
                            return [
                                'status'  => 'failed',
                                'code'    => 'ACCOUNT_NEED_VERIFY',
                                'desc'    => 'Login error',
                                'details' => 'Tài khoản yêu cầu xác nhận thông tin cá nhân trước khi tiếp tục'
                            ];
                        case '100':
                        case '0127':
                            //Login locked for 1 hour due too many wrong input
                            return [
                                'status'  => 'failed',
                                'code'    => 'ACCOUNT_LOCKED',
                                'desc'    => 'Login error',
                                'details' => 'Tài khoản bị khóa 1 giờ do sai thông tin đăng nhập quá nhiều lần'
                            ];
                        case 'IB01':
                            //Wrong captcha, try again
                            return $this->login( $username, $password );
                    }
                } else {
                    return [
                        'status'  => 'failed',
                        'code'    => 'BANK_OFFLINE',
                        'desc'    => 'Login error',
                        'details' => $query_resp
                    ];
                }
            } else {
                //Something wrong with login
                return [
                    'status'  => 'failed',
                    'code'    => 'BANK_OFFLINE',
                    'desc'    => 'Login error',
                    'details' => $query_resp
                ];
            }

        }

        //Something wrong with login
        return [
            'status' => 'failed',
            'code'   => 'BANK_OFFLINE',
            'desc'   => 'Unhandled exception on login()'
        ];
    }

    public function check_balance( $username, $password = null ) {
        $check_login = $this->login( $username, $password );
        if ( $check_login['status'] == 'success' ) {
            $balance_data = $this->getListAccount();
            if ( $balance_data['status'] == 'success' ) {
                return [
                    'status'  => 'success',
                    'code'    => 'GET_BALANCE_SUCCESS',
                    'desc'    => 'GET_BALANCE_SUCCESS',
                    'details' => $balance_data['data']->listDDAccounts
                ];
            }
        } else {
            return $check_login;
        }

        return [
            'status' => 'failed',
            'code'   => 'BANK_OFFLINE',
            'desc'   => 'Bank service unavailable at check_balance()',
        ];
    }

    public function get_history( $username, $password, $from = null, $to = null ) {
        //Date value example 24/08/2022
        $from_date   = $from ?? Carbon::now()->subDay()->format( 'd/m/Y' );
        $to_date     = $to ?? Carbon::now()->format( 'd/m/Y' );
        $check_login = $this->login( $username, $password );
        if ( $check_login['status'] == 'success' ) {
            $all_accounts = $this->getListAccount();
            if ( $all_accounts['status'] == 'success' ) {
                if ( count( $all_accounts['data']->listDDAccounts ) > 0 ) {
                    $resp_data = array();
                    $resp_code = -1;
                    $query_resp = false;
                    foreach ( $all_accounts['data']->listDDAccounts as $account ) {
                        $data         = [
                            "accountNo"    => $account['accountNo'],
                            "accountType"  => $account['accountType'],
                            "fromDate"     => $from_date,
                            "toDate"       => $to_date,
                            "stmtDate"     => "",
                            "stmtType"     => "",
                            "pageIndex"    => 0,
                            "mid"          => 14,
                            "lengthInPage" => 999999
                        ];
                        $encrypted_data   = $this->get_rsa_encrypted( $data );
                        $query_resp_encrypted   = $this->vcb_request( $this->config['get_history_url'], $encrypted_data );
                        $query_resp = $this->get_rsa_decrypted( $query_resp_encrypted );
                        if ( isset( $query_resp->code ) ) {
                            $resp_code = $query_resp->code;
                            if ( $query_resp->code === "00" ) {
                                $resp_data[ $account['accountNo'] ] = $query_resp->transactions;

                            }else{
                                # report to telegram due to get history error
                                $msg = '1in88 Can not get VCB history error '.json_encode($query_resp, JSON_UNESCAPED_UNICODE);
                                Helpers::send_telegram($msg);
                            }
                        }
                    }

                    return [
                        'status' => 'success',
                        'code'   =>  $resp_code,
                        'using_session' => $this->using_session,
                        'server_response' => $query_resp,
                        'data'   => $resp_data
                    ];
                } else {
                    return [
                        'status' => 'failed',
                        'code'   => 'BANK_OFFLINE',
                        'desc'   => 'User as no available account',
                    ];
                }
            }
        } else {
            return $check_login;
        }

        return [
            'status' => 'failed',
            'code'   => 'BANK_OFFLINE',
            'desc'   => 'Bank service unavailable at get_history()',
        ];
    }

    public function transfer_local($username, $password, $receiver_name, $receiver_bank, $amount, $note){
        $create_transfer_status = $this->create_transfer_local($username, $password, $receiver_name, $receiver_bank, $amount, $note);
        if($create_transfer_status['status'] == 'success'){
            $transaction = $create_transfer_status['details'];
            return $this->confirm_transfer($transaction);
        }else{
            return $create_transfer_status;
        }
    }
    public function submit_otp($username, $tranId, $challenge, $otp, $type='247'){
        $userDataQuery = VcbSessions::query();
        $userDataQuery->where('username', $username);
        $userDataQuery->where('bank', 'VCB');
        $user = $userDataQuery->first();
        $this->sessionId = $user->sessionId;
        $this->cif = $user->userInfo['cif'];
        $this->mobileId = $user->userInfo['mobileId'];
        $this->user = $username;
        $this->clientId = $user->userInfo['clientId'];

        $data = [
            "challenge"=> $challenge,
            "mid"=> 18,
            "otp"=> $otp,
            "tranId"=> $tranId
        ];
        $encrypted_data   = $this->get_rsa_encrypted( $data );
        if($type === '247'){

            $query_resp_encrypted   = $this->vcb_request( $this->config['confirm_otp_url'], $encrypted_data );
            $response = $this->get_rsa_decrypted( $query_resp_encrypted );
        }else{
            $query_resp_encrypted   = $this->vcb_request( $this->config['confirm_local_otp_url'], $encrypted_data );
            $response = $this->get_rsa_decrypted( $query_resp_encrypted );
        }

        $response_arr = $response;
        Log::debug('VCB submit OTP response');
        Log::debug(json_encode($response, JSON_UNESCAPED_UNICODE));

        if(isset($response_arr->code)){
            switch ($response_arr->code ){
                case '9805':
                case '019997':
                    Log::debug('Unhandled exception on submit_otp()' );
                    Log::debug(json_encode($response_arr, JSON_UNESCAPED_UNICODE));
                    return [
                        'status' => 'failed',
                        'code'=>'BANK_OFFLINE',
                        'desc'  => 'Bank service unavailable at submit_otp()',
                        'details'=> $response_arr->des
                    ];
                case '6009':
                case '39':
                case '0123001':
                    Log::debug('WRONG OTP on submit_otp()' );
                    Log::debug(json_encode($response_arr, JSON_UNESCAPED_UNICODE));
                    return [
                        'status' => 'failed',
                        'code'=>'WRONG_OTP',
                        'desc'  => 'WRONG_OTP',
                        'details'=> $response_arr->des
                    ];
                case '02':
                    Log::debug('SYSTEM DOES NOT SUPPORT THIS TYPE OF TRANSACTION' );
                    Log::debug(json_encode($response_arr, JSON_UNESCAPED_UNICODE));
                    return [
                        'status' => 'failed',
                        'code'=>'BANK_OFFLINE',
                        'desc'  => 'SYSTEM DOES NOT SUPPORT THIS TYPE OF TRANSACTION',
                        'details'=> $response_arr->des
                    ];
                case 'EXP':
                case 'KICKOUT':
                case '108':
                    Log::debug('SESSION_EXPIRED' );
                    Log::debug(json_encode($response_arr, JSON_UNESCAPED_UNICODE));
                    return [
                        'status' => 'failed',
                        'code'=>'SESSION_EXPIRED',
                        'desc'  => 'SESSION_EXPIRED',
                        'details'=> $response_arr->des
                    ];
                case '00':
                    return [
                        'status' => 'success',
                        'code'=>'TRANSACTION_SUCCESS',
                        'desc'  => 'TRANSACTION_SUCCESS',
                        'details'=> $response_arr
                    ];
            }

        }else{
            Log::debug('Check balance error' );
            Log::debug(json_encode($response, JSON_UNESCAPED_UNICODE));
            return [
                'status' => 'failed',
                'code'=>'BANK_OFFLINE',
                'desc'  => 'Check balance error',
                'details'=> $response
            ];
        }
        Log::debug('Bank service unavailable at submit_otp()');
        Log::debug(json_encode($response, JSON_UNESCAPED_UNICODE));
        return [
            'status' => 'failed',
            'code'=>'BANK_OFFLINE',
            'desc'  => 'Bank service unavailable at submit_otp()'
        ];
    }
    private function confirm_transfer($transaction){
        # loop through all otp type
        $current_trans_type = null;
        $transfer_methods = $transaction['listMethods'];
        foreach($transfer_methods as $method) {
            if ($method == '5') {
                $current_trans_type = 'SOFT_OTP';
                break;
            } elseif ($method == '1') {
                $current_trans_type = 'SMS';

            } else {
                $current_trans_type = $method;
            }
        }
        if($current_trans_type != 'SOFT_OTP'){
            return [
                'status' => 'failed',
                'code'=>'CREATE_TRANSACTION_ERROR',
                'desc'  => 'OTP Type not supported',
                'details'=> 'Hệ thống chưa hỗ trợ phương thức xác thực '. $current_trans_type
            ];
        }
        # request for SOFT OTP
        $tran_id = $transaction['tranId'];
        $otp_challenge = $this->request_soft_otp($tran_id);
        if($otp_challenge['status'] == 'success') {
            # Add queue to auto get job
            $new_job = VcbJobs::query();
            $new_job->insert([
                'username' => $this->username,
                'challenge' => $otp_challenge['details']->challenge,
                'tranId' => $tran_id,
                'status' => 0,
                'note' => 'init',
                'created_at' => Carbon::now()->timestamp,
                'updated_at' => Carbon::now()->timestamp
            ]);
        }
        return $otp_challenge;


    }
    private function create_transfer_local($username, $password, $receiver_name, $receiver_bank, $amount, $note){
        $check_balance = $this->check_balance($username, $password);
        if($check_balance['status'] == 'success'){
            //Find available account
            $all_accounts = $check_balance['details'];
            if(count($all_accounts)>0){
                $selected_account = false;
                foreach ($all_accounts as $account){
                    $account_balance = intVal(str_replace(',', '', strval($account['availBalance'])));
                    $account_number = $account['accountNo'];
                    if($account_balance >= intval($amount)){
                        $selected_account = $account_number;
                        break;
                    }
                }
                if($selected_account){
                    $data = [
                        "amount"=> strval($amount),
                        "ccyType"=> "",
                        "content"=> $note,
                        "activeTouch"=> "0",
                        "creditAccountNo"=> $receiver_bank,
                        "debitAccountNo"=> $selected_account,
                        "feeType"=> "1",
                        "mid"=> 16
                    ];
                    $encrypted_data   = $this->get_rsa_encrypted( $data );
                    $query_resp_encrypted   = $this->vcb_request( $this->config['init_transfer_url'], $encrypted_data );
                    $query_resp = $this->get_rsa_decrypted( $query_resp_encrypted );
                    if(isset($query_resp->code)){
                        switch ($query_resp->code ){
                            case '9805':
                                Log::debug('Unhandled exception on create_transfer_local()');
                                Log::debug(json_encode($query_resp, JSON_UNESCAPED_UNICODE));
                                return [
                                    'status' => 'failed',
                                    'code'=>'BANK_OFFLINE',
                                    'desc'  => 'Bank service unavailable at create_transfer_local()',
                                    'details'=> $query_resp->des
                                ];
                            case '065':
                            case '2004':
                                return [
                                    'status' => 'failed',
                                    'code'=>'WRONG_RECEIVER_INFORMATION',
                                    'desc'  => 'WRONG_RECEIVER_INFORMATION',
                                    'details'=> $query_resp->des
                                ];
                            case 'EXP':
                            case 'KICKOUT':
                            case '108':
                                return [
                                    'status' => 'failed',
                                    'code'=>'SESSION_EXPIRED',
                                    'desc'  => 'SESSION_EXPIRED',
                                    'details'=> $query_resp->des
                                ];
                            case '00':
                                if(isset($query_resp->transaction)){
                                    $new_receiver_name = $query_resp->transaction['creditAccountName'];
                                    if(trim(strtolower($new_receiver_name)) == trim(strtolower($receiver_name))){
                                        return [
                                            'status' => 'success',
                                            'code'=>'CREATE_TRANSFER_LOCAL_SUCCESS',
                                            'desc'  => 'CREATE_TRANSFER_LOCAL_SUCCESS',
                                            'details'=> $query_resp->transaction
                                        ];
                                    }else{
                                        return [
                                            'status' => 'failed',
                                            'code'=>'WRONG_RECEIVER_INFORMATION',
                                            'desc'  => $receiver_name .' != '.$new_receiver_name,
                                            'details'=> $query_resp->transaction
                                        ];
                                    }

                                }else{
                                    return [
                                        'status' => 'failed',
                                        'code'=> 'FAILED_GET_RECEIVER',
                                        'desc'  => $query_resp->code,
                                        'details'=> $query_resp->des
                                    ];
                                }
                        }

                    }else{
                        Log::debug('Unhandled exception on create_transfer_local()');
                        Log::debug(json_encode($query_resp, JSON_UNESCAPED_UNICODE));
                        return [
                            'status' => 'failed',
                            'code'=>'BANK_OFFLINE',
                            'desc'  => 'Transfer local error',
                            'details'=> $query_resp
                        ];
                    }
                }else{
                    return [
                        'status' => 'failed',
                        'code'=>'NOT_ENOUGH_BALANCE',
                        'desc'  => 'Tài khoàn không đủ số dư'
                    ];
                }
            }else{
                return [
                    'status' => 'failed',
                    'code'=>'NOT_ENOUGH_BALANCE',
                    'desc'  => 'Không có tài khoản khả dụng'
                ];
            }
        }else{
            return $check_balance;
        }
        Log::debug('Unhandled exception on transfer_local()');
        Log::debug(json_encode($check_balance, JSON_UNESCAPED_UNICODE));
        return [
            'status' => 'failed',
            'code'=>'BANK_OFFLINE',
            'desc'  => 'Bank service unavailable at transfer_local()'
        ];
    }
    private function create_transfer_247($username, $password, $receiver_name, $receiver_bank, $bankcode, $amount, $note){
        $check_balance = $this->check_balance($username, $password);
        if($check_balance['status'] == 'success'){
            //Find available account
            $all_accounts = $check_balance['details'];
            if(count($all_accounts)>0){
                $selected_account = false;
                foreach ($all_accounts as $account){
                    $account_balance = intVal(str_replace(',', '', strval($account['availBalance'])));
                    $account_number = $account['accountNo'];
                    if($account_balance >= intval($amount)){
                        $selected_account = $account_number;
                        break;
                    }
                }
                if($selected_account){
                    $data = [
                        "amount"=> $amount,
                        "ccyType"=> "1",
                        "content"=> $note,
                        "creditAccountNo"=> $receiver_bank,
                        "creditBankCode"=> $bankcode,
                        "debitAccountNo"=> $selected_account,
                        "feeType"=> "1",
                        "mid"=> 62
                    ];

                    $encrypted_data   = $this->get_rsa_encrypted( $data );
                    $query_resp_encrypted   = $this->vcb_request( $this->config['init_transfer_247_url'], $encrypted_data );
                    $query_resp = $this->get_rsa_decrypted( $query_resp_encrypted );
                    if(isset($query_resp->code)){
                        switch ($query_resp->code ){
                            case '9805':
                                Log::debug('Unhandled exception on chuyentienliennganhang_taikhoan()');
                                Log::debug(json_encode($query_resp, JSON_UNESCAPED_UNICODE));
                                return [
                                    'status' => 'failed',
                                    'code'=>'BANK_OFFLINE',
                                    'desc'  => 'Bank service unavailable at chuyentienliennganhang_taikhoan()',
                                    'details'=> $query_resp->des
                                ];
                            case '2004':
                                return [
                                    'status' => 'failed',
                                    'code'=>'WRONG_RECEIVER_INFORMATION',
                                    'desc'  => 'WRONG_RECEIVER_INFORMATION',
                                    'details'=> $query_resp->des
                                ];
                            case 'EXP':
                            case 'KICKOUT':
                            case '108':
                                return [
                                    'status' => 'failed',
                                    'code'=>'SESSION_EXPIRED',
                                    'desc'  => 'SESSION_EXPIRED',
                                    'details'=> $query_resp->des
                                ];
                            case '00':
                                if(isset($query_resp->transaction)){
                                    $new_receiver_name = $query_resp->transaction['creditAccountName'];
                                    if(trim(strtolower($new_receiver_name)) == trim(strtolower($receiver_name))){
                                        return [
                                            'status' => 'success',
                                            'code'=>'CREATE_TRANSFER_247_SUCCESS',
                                            'desc'  => 'CREATE_TRANSFER_247_SUCCESS',
                                            'details'=> $query_resp->transaction
                                        ];
                                    }else{
                                        return [
                                            'status' => 'failed',
                                            'code'=>'WRONG_RECEIVER_INFORMATION',
                                            'desc'  => $receiver_name .' != '.$new_receiver_name,
                                            'details'=> $query_resp->transaction
                                        ];
                                    }

                                }else{
                                    return [
                                        'status' => 'failed',
                                        'code'=> 'FAILED_GET_RECEIVER',
                                        'desc'  => $query_resp->code,
                                        'details'=> $query_resp->des
                                    ];
                                }
                        }

                    }else{
                        return [
                            'status' => 'failed',
                            'code'=>'ENCRYPTION_ERROR',
                            'desc'  => 'Transfer local error',
                            'details'=> $query_resp
                        ];
                    }
                }else{
                    return [
                        'status' => 'failed',
                        'code'=>'NOT_ENOUGH_BALANCE',
                        'desc'  => 'Tài khoàn không đủ số dư'
                    ];
                }
            }else{
                return [
                    'status' => 'failed',
                    'code'=>'NOT_ENOUGH_BALANCE',
                    'desc'  => 'Không có tài khoản khả dụng'
                ];
            }
        }else{
            return $check_balance;
        }
        Log::debug('Bank service unavailable at CREATE_TRANSFER_247()' );
        Log::debug(json_encode($check_balance, JSON_UNESCAPED_UNICODE));
        return [
            'status' => 'failed',
            'code'=>'BANK_OFFLINE',
            'desc'  => 'Bank service unavailable at CREATE_TRANSFER_247()'
        ];
    }
    private function request_soft_otp($tran_id){
        $data = [
            "mid"=> 17,
            "tranId"=> $tran_id,
            "type"=> 5,
        ];

        $encrypted_data   = $this->get_rsa_encrypted( $data );
        $query_resp_encrypted   = $this->vcb_request( $this->config['gen_otp_url'], $encrypted_data );
        $response_arr = $this->get_rsa_decrypted( $query_resp_encrypted );

        if(isset($response_arr->code)){
            switch ($response_arr->code ){
                case '9805':
                    Log::debug('Bank service unavailable at request_soft_otp()');

                    Log::debug(json_encode($response_arr, JSON_UNESCAPED_UNICODE));
                    return [
                        'status' => 'failed',
                        'code'=>'BANK_OFFLINE',
                        'desc'  => 'Bank service unavailable at request_soft_otp()',
                        'details'=> $response_arr->des
                    ];
                case 'EXP':
                case 'KICKOUT':
                case '108':
                    return [
                        'status' => 'failed',
                        'code'=>'SESSION_EXPIRED',
                        'desc'  => 'SESSION_EXPIRED',
                        'details'=> $response_arr->des
                    ];
                case '00':
                    return [
                        'status' => 'success',
                        'code'=>'REQUEST_OTP_SUCCESS',
                        'desc'  => 'REQUEST_OTP_SUCCESS',
                        'details'=> $response_arr,
                    ];

            }

        }else{
            Log::debug('Bank service unavailable at Transfer local error()' );

            Log::debug(json_encode($response_arr, JSON_UNESCAPED_UNICODE));
            return [
                'status' => 'failed',
                'code'=>'BANK_OFFLINE',
                'desc'  => 'Transfer local error',
                'details'=> $response_arr,
                'using_session' => $this->using_session,
                'server_response' => $response_arr,
            ];
        }
        Log::debug('Request OTP failed');

        Log::debug(json_encode($response_arr, JSON_UNESCAPED_UNICODE));
        return [
            'status' => 'failed',
            'code'=>'BANK_OFFLINE',
            'using_session' => $this->using_session,
            'server_response' => $response_arr,
            'data'   => $response_arr

        ];
    }




}

//Example

//Basic functions
$vcb_api = new VietcombankAPI();
$history = $vcb_api->get_history($user, $pwd, $from,$to);
$balance = $vcb_api->check_balance($user, $pwd);

//create transfer

$transfer_247 = $vcb_api->create_transfer_247($username, $password, $receiver_name, $receiver_bank, $bankcode, $amount, $note);
$transfer_local = $vcb_api->create_transfer_local($username, $password, $receiver_name, $receiver_bank, $amount, $note);
// return [
//     'status' => 'success',
//     'code'=>'REQUEST_OTP_SUCCESS',
//     'desc'  => 'REQUEST_OTP_SUCCESS',
//     'details'=> {challenge_id, transaction_id...},
// ];


// Submit OTP
$submit_result = $vcb_api->submit_otp($username, $tranId, $challenge, $otp, $type='247');
