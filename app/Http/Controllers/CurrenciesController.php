<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Historical_price;
use App\Latest_price;

class CurrenciesController extends Controller
{
	public function __construct()
	{
		header('Access-Control-Allow-Origin: *');
		header('Content-type: application/json');
	}

    public function index()
    {
		return Latest_price::orderBy('id')
					->get();
					// ->toArray();
    }

    public function currency($currency)
    {
		return Historical_price::where([
						'pair' => strtoupper($currency) . 'USDT', 
						'time_interval' => '1d'
					])
					->orderBy('open_time', 'desc')
					->get();
					// ->toArray();
    }
}
