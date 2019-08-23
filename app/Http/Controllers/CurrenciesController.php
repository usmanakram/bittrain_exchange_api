<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Historical_price;
use App\Latest_price;

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
}
