<?php

namespace App\Http\Middleware\Frontend;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class FrontendAccessVeify
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $req, Closure $next): Response
    {
        $app_name = $req->header('app-name');
        $app_id = $req->header('app-id');
        $api_key = $req->header('api-key');
        $systemSavedConfig =  Config::get('System.SystemConfig.system');
        if (
            ($app_name === $systemSavedConfig['app_primary_info']['app_name']) &&
            ($app_id === $systemSavedConfig['api']['app_id']) &&
            ($api_key === $systemSavedConfig['api']['api_key']) &&
            ($systemSavedConfig['api']['status'] === "Active")
        ) {
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
