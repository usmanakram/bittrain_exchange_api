<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Trade_transaction;

class TradeTransactionsController extends Controller
{
	public function getTradeTransactionsData($pair_id)
	{
	    $transactions = Trade_transaction::with('buy_order:id,currency_pair_id')
			->whereHas('buy_order', function($query) use ($pair_id) {
				$query->whereCurrencyPairId($pair_id);
			})
			->orderBy('id', 'desc')
			->limit(3)
			->get();

		$transactions->map(function($item, $key) {
			$item['currency_pair_id'] = $item->buy_order->currency_pair_id;
			unset($item->buy_order);
		});

	    return $transactions;
	}

	public function getTradeTransactions($pair_id)
	{
		$transactions = $this->getTradeTransactionsData($pair_id);
		return response()->api($transactions);
	}
}
