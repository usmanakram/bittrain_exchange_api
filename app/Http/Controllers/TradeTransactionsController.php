<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
			->limit(25)
			->get()
			->makeVisible('created_at');

		$transactions->map(function($item, $key) {
			$item['currency_pair_id'] = $item->buy_order->currency_pair_id;
			unset($item->buy_order);
		});

	    return $transactions->all();
	}

	public function getTradeTransactions($pair_id)
	{
		$transactions = $this->getTradeTransactionsData($pair_id);
		return response()->api($transactions);
	}

	public function getTradeHistoryForChartData($pair_id)
	{
		// Response sample
		/*[
			{ time: '2019-10-11', open: '', high: '', low: '', close: ''},
			{ time: '2019-10-10', open: '', high: '', low: '', close: ''},
			{ time: '2019-10-09', open: '', high: '', low: '', close: ''},
			{ time: '2019-10-08', open: '', high: '', low: '', close: ''},
		]*/

		// $history = Trade_transaction::get()->makeVisible('created_at');


		/*$query = "
			SELECT
				FLOOR(MIN(`timestamp`)/"+period+")*"+period+" AS timestamp,
				SUM(amount) AS volume,
				SUM(price*amount)/sum(amount) AS wavg_price,
				SUBSTRING_INDEX(MIN(CONCAT(`timestamp`, '_', price)), '_', -1) AS `open`,
				MAX(price) AS high,
				MIN(price) AS low,
				SUBSTRING_INDEX(MAX(CONCAT(`timestamp`, '_', price)), '_', -1) AS `close`
			FROM transactions_history -- this table has 3 columns (timestamp, amount, price)
			GROUP BY FLOOR(`timestamp`/"+period+")
			ORDER BY timestamp";*/

		$period = 60*60;

		$query = "
			SELECT
				FLOOR(MIN(UNIX_TIMESTAMP(`created_at`))/" . $period . ")*" . $period . " AS time,
				SUM(quantity) AS volume,
				SUM(rate*quantity)/sum(quantity) AS wavg_price,
				SUBSTRING_INDEX(MIN(CONCAT(UNIX_TIMESTAMP(`created_at`), '_', rate)), '_', -1) AS `open`,
				MAX(rate) AS high,
				MIN(rate) AS low,
				SUBSTRING_INDEX(MAX(CONCAT(UNIX_TIMESTAMP(`created_at`), '_', rate)), '_', -1) AS `close`
			FROM trade_transactions 
			GROUP BY FLOOR(UNIX_TIMESTAMP(`created_at`)/" . $period . ")
			ORDER BY time";

		return DB::select(DB::raw($query));
	}

	public function getTradeHistoryForChart(Request $request, $pair_id)
	{
		// Form validation

		$history = $this->getTradeHistoryForChartData($pair_id);

		return response()->api($history);
	}
}
