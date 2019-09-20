<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Historical_price;
use App\Latest_price;
use App\Currency;

class CurrenciesController extends Controller
{
	/*public function __construct()
	{
		header('Access-Control-Allow-Origin: *');
		header('Content-type: application/json');
	}*/

    public function index()
    {
		$response = Latest_price::orderBy('id')
					->get();
					// ->toArray();
		
		// return response()->json($response);
		return response()->api($response);
    }

    public function currency($currency)
    {
		$response = Historical_price::where([
						'pair' => strtoupper($currency) . 'USDT', 
						'time_interval' => '1d'
					])
					->orderBy('open_time', 'desc')
					->get();
					// ->toArray();

		// return response()->json($response);
		return response()->api($response);
    }

	public function getAllCurrencies()
	{
		return response()->api( Currency::get(['name', 'symbol']) );
	}

	public function getBalances(Request $request)
	{
		$balances = Currency::with(['balances' => function($query) use ($request) {
				$query->select('currency_id', 'total_balance', 'in_order_balance')
					->where('user_id', $request->user()->id);
			}])
			->get(['id', 'name', 'symbol']);

		$balances->map(function($item) {
			$item['total_balance'] = $item['balances'][0]['total_balance'] ?? 0;
			$item['in_order_balance'] = $item['balances'][0]['in_order_balance'] ?? 0;
			unset($item['balances']);
			return $item;
		})
		->all();

		return response()->api($balances);
	}
}
