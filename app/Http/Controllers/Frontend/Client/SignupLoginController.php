<?php

namespace App\Http\Controllers\Frontend\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientLoginSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Haruncpi\LaravelIdGenerator\IdGenerator as IDGen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class SignupLoginController extends Controller
{
    protected $status;
    protected $code;
    protected $error;
    protected $msg;
    protected $systemConfig;

    public function __construct()
    {
        $this->status = true;
        $this->code   = 200;
        $this->error  = null;
        $this->msg    = null;
    }
    public function SignUp(Request $req)
    {
        try {
            $validator = Validator::make($req->all(), [
                'phone'    => 'numeric|unique:clients',
                'email'    => 'required|email|unique:clients',
                'password' => 'required|string',
            ], [
                'phone.required'    => 'phone field is required.',
                'phone.numeric'     => 'Phone must be numeric.',
                'phone.unique'      => 'Phone already exists.',
                'email.required'    => 'email field is required.',
                'email.email'       => 'Invalid email format.',
                'email.unique'      => 'Email already exists.',
                'password.required' => 'password field is required.',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => 'bad-request',
                    'msg'    => $validator->errors()->first(),
                ], 200);
            } else {
                $phone = $req->phone;
                $email = $req->email;
                $password = $req->password;
                $hashedPassword = Hash::make($password);

                $ran_sl = Str::random(5);
                $appName = config('System.SystemConfig.system.app_primary_info.app_name');
                $words = explode(' ', $appName);
                $prefix = '';
                foreach ($words as $word) {
                    $prefix .= substr($word, 0, 2);
                }
                $prefix = strtoupper($prefix);

                $serial = IDGen::generate(['table' => 'clients', 'field' => 'serial', 'length' => 12, 'prefix' => '0']);
                $client_id = str_replace(' ', '_', $prefix) . 'clnt' . hexdec(uniqid()) . $ran_sl . $serial;

                $new_client = new Client;
                $new_client->client_id = $client_id;
                $new_client->serial = $serial;
                $new_client->phone = $phone;
                $new_client->email = $email;
                $new_client->password = $hashedPassword;
                $new_client->save();

                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = "Registered successfully";

                return response()->json([
                    'status' => $this->status,
                    'code'   => $this->code,
                    'error'  => $this->error,
                    'msg'    => $this->msg,
                ], 200);
            }
        } catch (Exception $e) {
            $this->status = false;
            $this->code = 422;
            $this->error = "Unprocessable";
            $this->msg = $e->getMessage() . ' in line ' . $e->getLine();

            return response()->json([
                'status' => $this->status,
                'code' => $this->code,
                'error' => $this->error,
                'msg' => $this->msg,
            ], 200);
        }
    }

    public function ClientLogin(Request $req)
    {
        try {
            $validator = Validator::make($req->all(), [
                'email'        => 'required',
                'password'     => 'required',
                'user_agent'   => 'required',
            ], [
                'email.required'      => 'Email field is required.',
                'email.email'         => 'Invalid email format.',
                'password.required'   => 'Password field is required.',
                'user_agent.required' => 'User agent field is required.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code'   => 400,
                    'error'  => 'Bad Request',
                    'msg'    => $validator->errors()->first(),
                ], 400);
            }

            $identifier = $req->input('email');
            $password   = $req->input('password');
            $user_agent = $req->input('user_agent');

            $field  = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            $client = Client::where($field, $identifier)->first();

            if (!$client) {
                return response()->json([
                    'status' => false,
                    'code'   => 404,
                    'error'  => 'Not Found',
                    'msg'    => 'Email not found, please register',
                ], 404);
            }

            if (Hash::check($password, $client->password)) {
                $serial = $client->serial;
                $genAccessTokenAndTimeLimit = $this->GenerateAccessToken($serial, $identifier);
                $accessToken = $genAccessTokenAndTimeLimit['accessToken'];
                $time_limit = $genAccessTokenAndTimeLimit['time_limit'];
                $key = $genAccessTokenAndTimeLimit['key'];
                $body = [
                    'identifier' => $identifier,
                    'client_id'  => $client->client_id,
                    'accessToken' => $accessToken,
                    'time_limit' => $time_limit,
                    'user_agent' => $user_agent,
                ];
                $new_session = new ClientLoginSession();
                $new_session->client_id = $client->client_id;
                $new_session->key = $key;
                $new_session->identifier = $identifier;
                $new_session->access_token = $accessToken;
                $new_session->user_agent = $user_agent;
                $new_session->time_limit = $time_limit;
                $new_session->save();

                Cache::put($key, $body, $time_limit);
                return response()->json([
                    'status' => true,
                    'code'   => 200,
                    'error'  => "Verified",
                    'msg'    => "Logged in successfully",
                    'data'   => [
                        'identifier' => $identifier,
                        'key' => $key,
                        'accesstoken' => $accessToken,
                    ]
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'code'   => 401,
                    'error'  => 'Unauthorized',
                    'msg'    => 'Wrong password',
                ], 401);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'code'   => 500,
                'error'  => 'Internal Server Error',
                'msg'    => $e->getMessage() . ' in line ' . $e->getLine(),
            ], 500);
        }
    }

    private function GenerateAccessToken($serial, $identifier)
    {
        try {
            $validity_days = Config::get('AppConfig.AppConfig.session.user');
            $timezone = config('app.timezone');
            $currentDateTime = Carbon::now($timezone);
            $time_limit = $currentDateTime->addDays($validity_days);
            $rendText = Str::random(10);
            $uniquID = hexdec(uniqid());
            $dateTime = date('ymdhms');
            $identifierWithoutDot = str_replace('.', '', $identifier);
            $genAccessToken = Hash::make($rendText . $uniquID . $serial . $identifier . $dateTime);
            $genAccessToken = preg_replace('/[\/.$]/', '', $genAccessToken);
            $key = $identifierWithoutDot . $rendText . $dateTime;
            return ['accessToken' => $genAccessToken, 'time_limit' => $time_limit, 'key' => $key];
        } catch (Exception $e) {
            throw $e;
        }
    }
}
