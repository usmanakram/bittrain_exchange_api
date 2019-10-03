<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Historical_price;
use App\Latest_price;
use App\Currency;
use App\Currency_pair;

class CurrenciesController extends Controller
{
	/*public function __construct()
	{
		header('Access-Control-Allow-Origin: *');
		header('Content-type: application/json');
	}*/

    public function index()
    {
		/*$response = Latest_price::orderBy('id')
					->get();*/
		$response = Currency_pair::with('latest_price')->whereStatus(true)->orderBy('id')->get();
		
		return response()->api($response);
    }

    public function currency($currency)
    {
		/*$response = Historical_price::where([
						'pair' => strtoupper($currency) . 'USDT', 
						'time_interval' => '1d'
					])
					->orderBy('open_time', 'desc')
					->get();*/
		
		$response = Currency_pair::with(['historical_prices' => function($query) {
						$query->select('id','currency_pair_id','open','high','low','close','volume','open_time')
							->orderBy('open_time', 'desc');
					}])
					/*->whereHas('base_currency', function($query) use ($currency) {
						$query->whereSymbol($currency);
					})
					->whereHas('quote_currency', function($query) {
						$query->whereSymbol('USDT');
					})*/
					->whereSymbol(strtoupper($currency) . 'USDT')
					->first();

		return response()->api($response);
    }

	public function getAllCurrencies()
	{
		// return response()->api( Currency::get(['name', 'symbol']) );
		return response()->api( Currency::where('symbol', '!=', 'BC')->get(['name', 'symbol']) );
	}

	private function getDollarValue($currency, $amount)
	{
		return Latest_price::wherePair(strtoupper($currency) . 'USDT')->first()->last_price * $amount;
	}
}
