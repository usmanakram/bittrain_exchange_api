<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;

class ResponseMacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Response::macro('api', function($response, $code = 200) {
            // return response()->json($response)->header('Access-Control-Allow-Origin', '*');
            return Response::json($response, $code)->header('Access-Control-Allow-Origin', '*');
        });
    }
}
