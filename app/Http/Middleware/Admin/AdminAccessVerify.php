<?php

namespace App\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class AdminAccessVerify
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $req, Closure $next): Response
    {
        $admin_key =  strval(Config::get('System.SystemConfig.admin_key'));
        $auth_key = $req->header('auth-key');
        if($auth_key === $admin_key){
            return $next($req);
        }else{
            return response()->json([
                'status' => false,
                'code' => 401,
                'error' => 'Unauthorized',
                'msg' => "Sorry you are not authorize to access",
            ], 200);
        }
    }
}
