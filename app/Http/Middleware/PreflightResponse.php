<?php

namespace App\Http\Middleware;

use Closure;

class PreflightResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->getMethod() === "OPTIONS") {
            // echo 'good to see you';
            // return response('');
            
            // https://stackoverflow.com/questions/34748981/laravel-5-2-cors-get-not-working-with-preflight-options
            /*return response('')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type');*/
            return response()->api('', 204)->header('Access-Control-Allow-Headers', 'Authorization');
        }

        return $next($request);
    }
}
