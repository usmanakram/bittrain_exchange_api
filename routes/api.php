<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


Route::get('/test', function(Request $request) {
	echo 'good to see you';
});

Route::get('/cron-1day', 'CronsController@cron_1day');
Route::get('/cron-1min', 'CronsController@cron_1min');

Route::get('/currencies', 'CurrenciesController@index');
Route::get('/currencies/{currency}', 'CurrenciesController@currency');




Route::get('/test-get-apiendpoint', 'UsersController@testApiEndpoint');
Route::post('/test-post-apiendpoint', 'UsersController@testApiEndpoint');

Route::get('/login', 'UsersController@login');
Route::get('/test-curl', 'UsersController@testCurl');

Route::post('/react-login', 'UsersController@reactLogin');
// Route::options('/react-login', 'UsersController@reactLogin');


/*Route::group([
    'prefix' => 'auth'
], function () {
    Route::post('login', 'AuthController@login');
    Route::post('signup', 'AuthController@signup');
  
    Route::group([
      'middleware' => 'auth:api'
    ], function() {
        Route::get('logout', 'AuthController@logout');
        Route::get('user', 'AuthController@user');
    });
});*/