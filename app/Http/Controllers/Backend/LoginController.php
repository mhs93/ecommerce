<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LoginController extends Controller
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
        $this->systemConfig =  app('system_config');
    }

    public function LoginAuthSave(Request $req)
    {
        try {
            /**
             * Request body
             * Request query param time
             * Save it in to cache with email + auth text key
             * [It will authenticate if user is logged in or not]
             */

            $time_limit = $req->input('time_limit');
            $body = $req->input('token');
            if (empty($time_limit) || empty($body)) {

                $this->status = true;
                $this->code = 400;
                $this->error = "Verified";
                $this->msg = "Please provide all info";

                return response()->json([
                    'status' => $this->status,
                    'code' => $this->code,
                    'error' => $this->error,
                    'msg' => $this->msg,
                ], 200);
            } else {
                $timezone = config('app.timezone');
                $currentDateTime = Carbon::now($timezone);


                $newDateTime = $currentDateTime->addDays($time_limit);
                $key = $body['key'];
                Cache::put($key, $body, $newDateTime);



                $this->status = true;
                $this->code = 200;
                $this->error = "Verified";
                $this->msg = "Access Granted";

                return response()->json([
                    'status' => $this->status,
                    'code' => $this->code,
                    'error' => $this->error,
                    'msg' => $this->msg,
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

    public function LogoutAuthRemove(Request $req)
    {
        try {
            $key = $req->input('key');
            if (empty($key)) {
                $this->status = true;
                $this->code = 400;
                $this->error = "Verified";
                $this->msg = "Please provide key";

                return response()->json([
                    'status' => $this->status,
                    'code' => $this->code,
                    'error' => $this->error,
                    'msg' => $this->msg,
                ], 200);
            } else {
                if (Cache::has($key)) {
                    Cache::forget($key);
                    $this->status = true;
                    $this->code = 200;
                    $this->error = "Verified";
                    $this->msg = "Access Removed";
                } else {
                    $this->status = true;
                    $this->code = 200;
                    $this->error = "Verified";
                    $this->msg = "Access already Removed";
                }
                return response()->json([
                    'status' => $this->status,
                    'code' => $this->code,
                    'error' => $this->error,
                    'msg' => $this->msg,
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
}
