<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class AdminController extends Controller
{
    /**
     * Sohag
     *01671343973
     */
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

    public function Index(){
        $appName = $this->systemConfig['app_primary_info']['app_name'];
        return response()->json([
            'app_name' => $appName,
        ],200);
    }


    public function UpdateSystemConfig(Request $req){
        try {
            $app_name = $req->input('app_name');
            $author_mail = $req->input('author_mail');
            $domain = $req->input('domain');
            $api_key = $req->input('api_key');
            $app_id = $req->input('app_id');
            $status = $req->input('status');

            $admin_key = strval(Config::get('System.SystemConfig.admin_key'));
            $configArray = [
                'admin_key' => $admin_key,
                'system' => [
                    'app_primary_info' => [
                        'app_name' => $app_name,
                        'author_mail' => $author_mail,
                        'domain' => $domain,
                    ],
                    'api' => [
                        'api_key' => $api_key,
                        'app_id' => $app_id,
                        'status' => $status,
                    ],
                ],
            ];

            $configFilePath = config_path('System/SystemConfig.php');

            file_put_contents($configFilePath, '<?php return ' . var_export($configArray, true) . ';');


            $this->status = true;
            $this->code = 200;
            $this->error = "Verified";
            $this->msg = "System Config updated";

            return response()->json([
                'status' => $this->status,
                'code' => $this->code,
                'error' => $this->error,
                'msg' => $this->msg,
            ], 200);

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
