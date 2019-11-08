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

Route::get('/currency-pairs', 'CurrencyPairsController@index');

Route::get('/latest-prices', 'CurrencyPairsController@latestPrices');

Route::get('/order-book/{pair_id}', 'TradeOrdersController@getOrderBook');
Route::get('/trade-history/{pair_id}', 'TradeTransactionsController@getTradeTransactions');
Route::get('/chart-trade-history/{pair_id}', 'TradeTransactionsController@getTradeHistoryForChart');


Route::get('/trade-engine-testing/{tradeOrder}', 'TradeOrdersController@tradeEngineTesting');




Route::get('/test-get-apiendpoint', 'UsersController@testApiEndpoint');
Route::post('/test-post-apiendpoint', 'UsersController@testApiEndpoint');

Route::post('/coinpayments-ipn/{user_id}', 'IpnsController@coinpayments');
Route::post('/coinpayments-withdrawal-ipn', 'IpnsController@coinpaymentsWithdrawal');

/*Route::get('/login', 'UsersController@login');
Route::get('/test-curl', 'UsersController@testCurl');

Route::post('/react-login', 'UsersController@reactLogin');*/
// Route::options('/react-login', 'UsersController@reactLogin');

Route::get('get-all-currencies', 'CurrenciesController@getAllCurrencies');
Route::post('deposit-bittrain', 'BittrainTransactionsController@depositFromBittrain');
Route::post('validate-bittrain-deposit', 'BittrainTransactionsController@validateBittrainDeposit');

Route::get('test-email', 'TransactionsController@testEmail');

Route::group([
    'prefix' => 'auth'
], function () {
    Route::post('login', 'AuthController@login');
    Route::post('signup', 'AuthController@signup');
  
    Route::group([
      'middleware' => 'auth:api'
    ], function() {
        Route::get('logout', 'AuthController@logout');
        Route::get('user', 'AuthController@user');
        
        Route::get('get-deposit-address/{currency}', 'TransactionsController@getDepositAddress');
        Route::get('get-transactions-history', 'TransactionsController@getTransactionsHistory');
        Route::post('withdraw', 'TransactionsController@requestToWithdraw');
        Route::get('get-balances', 'BalancesController@getBalances');
        
        Route::post('buy', 'TradeOrdersController@buy');
        Route::post('sell', 'TradeOrdersController@sell');
        Route::get('cancel-order/{order}', 'TradeOrdersController@cancelOrder');

        Route::post('/user-trades', 'TradeOrdersController@getUserTrades');
        Route::post('/user-orders', 'TradeOrdersController@getUserOrders');
        Route::post('/user-open-orders', 'TradeOrdersController@getUserOpenOrders');
    });
});


 Route::resource('users', 'UsersController');
    Route::get('/user-trades', 'TradeOrdersController@getUserTrades');
    /* overall open orders */
    Route::get('/open_orders', 'TradeOrdersController@getAllOpenOrdersData');
    /* overall deposits */
    Route::get('deposits', 'TransactionsController@getOverallDeposits');
    /* Pending withdrawals */
    Route::get('pending_withdraw', 'TransactionsController@getAllPendingWithdraw');
    /* Paid withdrawals */
    Route::get('paid_withdraw', 'TransactionsController@getAllPaidWithdraw');
    
    /* All Balances */
    Route::get('balances', 'BalancesController@getAllBalances');