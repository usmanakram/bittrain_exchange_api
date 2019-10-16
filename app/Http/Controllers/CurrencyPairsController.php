<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Currency_pair;

class CurrencyPairsController extends Controller
{
    public function index()
    {
    	$currency_pairs = Currency_pair::with('base_currency', 'quote_currency')->whereStatus(true)->orderBy('id')->get();
    	
    	$currency_pairs->map(function($item, $key) {
    		$item['base_currency_name'] = $item->base_currency->name;
    		$item['base_currency_symbol'] = $item->base_currency->symbol;
    		
    		$item['quote_currency_name'] = $item->quote_currency->name;
    		$item['quote_currency_symbol'] = $item->quote_currency->symbol;
    		
    		unset($item['base_currency']);
    		unset($item['quote_currency']);
    		
    		return $item;
    	});
		
		return response()->api($currency_pairs);
    }

    public function latestPricesData()
    {
        return Currency_pair::with('latest_price')->whereStatus(true)->orderBy('id')->get()->all();
    }

    public function latestPrices()
    {
        $prices = $this->latestPricesData();
        return response()->api($prices);
    }
}