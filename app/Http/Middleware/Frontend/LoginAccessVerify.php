<?php

namespace App\Http\Middleware\Frontend;

use App\Models\Client;
use App\Models\ClientLoginSession;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class LoginAccessVerify
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $identifier = $request->header('identifier');
        $key = $request->header('key');
        $timezone = config('app.timezone');
        $currentDateTime = Carbon::now($timezone);
        if (Cache::has($key)) {
            $cacheData = Cache::get($key);

            $setted_identifier = $cacheData['identifier'];
            $time_limit = $cacheData['time_limit'];
            $client_id = $cacheData['client_id'];

            if (($setted_identifier === $identifier) && ($time_limit >= $currentDateTime)) {
                $request->merge([
                    'identifier' => $identifier,
                    'client_id' => $client_id
                ]);

                return $next($request);
            } else {
                return response()->json([
                    'status' => false,
                    'code' => 403,
                    'error' => 'Access-forbidden',
                    'msg' => "key does not match 1",
                ], 200);
            }
        } else {
            $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            $clientActive = Client::where([
                [$field, $identifier],
                ['status', 'active']
            ])->exists();
            if ($clientActive === true) {
                $clientLoginData = ClientLoginSession::where([
                    ['identifier', '=', $identifier],
                    ['key', '=', $key],
                    ['time_limit', '>=', $currentDateTime],
                ])->exists();
                if ($clientLoginData === true) {
                    $client_id  = Client::where([
                        [$field, $identifier],
                        ['status', 'active']
                    ])->value('client_id');

                    $request->merge(['client_id' => $client_id]);
                    return $next($request);
                } else {
                    return response()->json([
                        'status' => false,
                        'code' => 403,
                        'error' => 'Access-forbidden',
                        'msg' => "key does not match 2",
                    ], 200);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'code' => 403,
                    'error' => 'Access-forbidden',
                    'msg' => "key does not match 3",
                ], 200);
            }
        }
    }
}
