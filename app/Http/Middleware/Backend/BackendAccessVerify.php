<?php

namespace App\Http\Middleware\Backend;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class BackendAccessVerify
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $req, Closure $next): Response
    {
        $key = $req->header('key');
        $email = $req->header('email');
        $access_token = $req->header('access-token');
        $accessedDevice = $req->header('accessedDevice');
        if (Cache::has($key)) {
            $get_user_data = Cache::get($key);
            if (isset($get_user_data['email']) && $email === $get_user_data['email'] && $access_token === $get_user_data['access_token'] && !empty($accessedDevice)) {
                $req->attributes->set('employee_email', $email);
                return $next($req);
            } else {
                return response()->json([
                    'status' => false,
                    'code' => 403,
                    'error' => 'Access-forbidden',
                    'msg' => "Sorry, you are not authorized to access",
                ], 200);
            }
        } else {
            $validate_main_server_user_access = $this->MainAPIServerUserAccessVerify($email, $access_token, $key, $accessedDevice);
            if ($validate_main_server_user_access["status"] === true && $validate_main_server_user_access["res"] === 200) {
                $main_data_scoope = $validate_main_server_user_access["redirection"]["data"];
                $timezone = config('app.timezone');
                $currentDateTime = Carbon::now($timezone);
                $timeToAdd = $main_data_scoope["time_left"]["time"];
                $newDateTime = $currentDateTime->copy()->addHours($timeToAdd);

                $bodyData = $main_data_scoope["login_data"] ?? null;
                $keyBody = $main_data_scoope["key"] ?? null;
                if (!empty($bodyData) && !empty($keyBody)) {
                    Cache::put($keyBody, $bodyData, $newDateTime);
                }
                $req->attributes->set('employee_email', $email);
                return $next($req);
            } else {
                return response()->json([
                    'status' => false,
                    'code' => 403,
                    'error' => 'Access-forbidden',
                    'msg' => "Sorry you are not authorize to access",
                ], 200);
            }
        }
    }

    private function MainAPIServerUserAccessVerify($email, $access_token, $key, $accessedDevice)
    {
        $main_url = config('AppConfig.AppConfig.main_system');
        $api_key = config('System.SystemConfig.system.api.api_key');
        $app_id = config('System.SystemConfig.system.api.app_id');
        $data =   Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'appId' => $app_id,
            'apiKey' => $api_key,
            'accessedDevice' => $accessedDevice,
        ])->get($main_url . '/service-pack/validate-user?email=' . $email . '&access_token=' . $access_token . '&key=' . $key);
        $decoded_data =  json_decode($data->getBody(), true);
        return $decoded_data;
    }
}
