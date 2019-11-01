<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Currency;

class BalancesController extends Controller
{
	public function getBalancesData($user_id)
	{
		// return $this->getDollarValue('btc', 14);
		$balances = Currency::with(['balances' => function($query) use ($user_id) {
				$query->select('currency_id', 'total_balance', 'in_order_balance')
					->where('user_id', $user_id);
			}])
			->get(['id', 'name', 'symbol']);

		$balances->map(function($item) use ($user_id) {
			// if balance does not exist for this currency
			if ( !isset($item['balances'][0]) ) {
				$item->balances()->create(['user_id' => $user_id, 'in_order_balance' => 0, 'total_balance' => 0]);

				$item['total_balance'] = $item['in_order_balance'] = 0;
			} else {
				$item['total_balance'] = $item['balances'][0]['total_balance'];
				$item['in_order_balance'] = $item['balances'][0]['in_order_balance'];
			}
			
			// $item['total_balance'] = $item['balances'][0]['total_balance'] ?? 0;
			// $item['in_order_balance'] = $item['balances'][0]['in_order_balance'] ?? 0;
			unset($item['balances']);
			return $item;
		})
		// ->all()
		;

		return $balances;
	}

    public function getBalances(Request $request)
	{
		/*// return $this->getDollarValue('btc', 14);
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
		// ->all()
		;*/
		$balances = $this->getBalancesData($request->user()->id);

		return response()->api($balances);
	}
}
