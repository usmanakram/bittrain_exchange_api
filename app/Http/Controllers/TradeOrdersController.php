<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Currency_pair;
use App\Latest_price;
use App\Trade_order;
use App\Balance;

class TradeOrdersController extends Controller
{
	private $fee = 0; // Percentage
	private $orderBookEntries = 10;

    public function buy(Request $request)
    {
    	$request->validate([
            'pair_id' => 'required',
            'price' => 'required',
            'quantity' => 'required',
        ]);

        DB::beginTransaction();

        try {
        	$user_id = $request->user()->id;

        	// $latest_price = Latest_price::whereCurrencyPairId($request->pair_id)->first()->last_price;
        	
			$balance = Currency_pair::with(['quote_currency.balances' => function($query) use ($user_id) {
				$query->whereUserId($user_id);
			}])->find($request->pair_id);

			$fee = ($request->quantity * $request->price) * ($this->fee / 100);
			$requiredBalance = ($request->quantity * $request->price) + $fee;

			$userBalance = $balance->quote_currency->balances[0];
			$availableBalance = $userBalance->total_balance - $userBalance->in_order_balance;

			if ($availableBalance >= $requiredBalance) {
				// Place an order
				$order = Trade_order::create([
					'user_id' => $user_id,
					'currency_pair_id' => $request->pair_id,
					'direction' => 1,
					'quantity' => $request->quantity,
					'rate' => $request->price,
					'fee' => $fee,
					'fee_currency_id' => $balance->base_currency_id,
					// 'trigger_rate' => NULL,
					'tradable_quantity' => $request->quantity,
					'type' => 1,
					'status' => 1,
				]);

				// Update available balance
				$update = Balance::where([
					'user_id' => $user_id, 
					'currency_id' => $userBalance->currency_id
				])
				->increment('in_order_balance', $requiredBalance);

				if ($order && $update) {
					DB::commit();

					// Broadcast OrderBook Data
					$orderBookData = $this->getOrderBookData($request->pair_id);
					event(new \App\Events\OrderBookUpdated( $orderBookData, $request->pair_id ));
					
					return response()->api('Buy Order Placed');

				} else {

					DB::rollBack();
					return response()->api('Some error occurred. Please, try again later.', 400);
				}

			} else {
				return response()->api('Insufficient balance', 400);
			}

        } catch (\Exception $e) {
        	DB::rollBack();
			
			$error_msg = "ERROR at \nLine: " . $e->getLine() . "\nFILE: " . $e->getFile() . "\nActual File: " . __FILE__ . "\nMessage: ".$e->getMessage();
            Log::error($error_msg);

			// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
			Log::channel('slack')->critical(
				"Trade Order Placement: \n" . 
				"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
				"*Data:* " . json_encode($request->all()) . "\n" . 
				"*Error:* " . $error_msg
			);

			return response()->api('Some error occurred. Please, try again later', 400);
        }

    }

    public function sell(Request $request)
    {
    	$request->validate([
            'pair_id' => 'required',
            'price' => 'required',
            'quantity' => 'required',
        ]);

        DB::beginTransaction();

        try {
        	$user_id = $request->user()->id;

        	// $latest_price = Latest_price::whereCurrencyPairId($request->pair_id)->first()->last_price;
        	
			$balance = Currency_pair::with(['base_currency.balances' => function($query) use ($user_id) {
				$query->whereUserId($user_id);
			}])->find($request->pair_id);


			$fee = ($request->quantity * $request->price) * ($this->fee / 100);
			// $requiredBalance = ($request->quantity * $request->price) + $fee;
			
			// $userBalance = $balance->quote_currency->balances[0];
			$userBalance = $balance->base_currency->balances[0];
			$availableBalance = $userBalance->total_balance - $userBalance->in_order_balance;

			// if ($availableBalance >= $requiredBalance) {
			if ($availableBalance >= $request->quantity) {
				// Place an order
				$order = Trade_order::create([
					'user_id' => $user_id,
					'currency_pair_id' => $request->pair_id,
					'direction' => 0,
					'quantity' => $request->quantity,
					'rate' => $request->price,
					'fee' => $fee,
					'fee_currency_id' => $balance->quote_currency_id,
					// 'trigger_rate' => NULL,
					'tradable_quantity' => $request->quantity,
					'type' => 1,
					'status' => 1,
				]);

				// Update available balance
				$update = Balance::where([
					'user_id' => $user_id, 
					'currency_id' => $userBalance->currency_id
				])
				// ->increment('in_order_balance', $requiredBalance);
				->increment('in_order_balance', $request->quantity);

				if ($order && $update) {
					DB::commit();

					// Broadcast OrderBook Data
					$orderBookData = $this->getOrderBookData($request->pair_id);
					event(new \App\Events\OrderBookUpdated( $orderBookData, $request->pair_id ));

					return response()->api('Sell Order Placed');

				} else {

					DB::rollBack();
					return response()->api('Some error occurred. Please, try again later.', 400);
				}

			} else {
				return response()->api('Insufficient balance', 400);
			}

        } catch (\Exception $e) {
        	DB::rollBack();
			
			$error_msg = "ERROR at \nLine: " . $e->getLine() . "\nFILE: " . $e->getFile() . "\nActual File: " . __FILE__ . "\nMessage: ".$e->getMessage();
            Log::error($error_msg);

			// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
			Log::channel('slack')->critical(
				"Trade Order Placement: \n" . 
				"*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
				"*Data:* " . json_encode($request->all()) . "\n" . 
				"*Error:* " . $error_msg
			);

			return response()->api('Some error occurred. Please, try again later', 400);
        }

    }

    private function getOrderBookData($pair_id)
    {
    	$where = [
    		'currency_pair_id' => $pair_id,
    		'direction' => 1, // buy orders
    		'type' => 1, // limit orders
    		'status' => 1, // available for trade
    	];

    	DB::statement("SET sql_mode = '' ");

    	$buyOrders = Trade_order::where($where)
    		->select(DB::raw('id, rate, SUM(tradable_quantity) AS tradable_quantity'))
    		->groupBy('rate')
    		->orderBy('rate', 'desc')
    		->limit($this->orderBookEntries)
    		->get();
    	
    	$where['direction'] = 0;
    	$sellOrders = Trade_order::where($where)
    		->select(DB::raw('id, rate, SUM(tradable_quantity) AS tradable_quantity'))
    		->groupBy('rate')
    		->orderBy('rate', 'desc')
    		->limit($this->orderBookEntries)
    		->get();
    	
    	// return response()->api($buyOrders);
    	return compact('buyOrders', 'sellOrders');
    }

    public function getOrderBook(Request $request, $pair_id = null)
    {
    	if ( is_null($pair_id) ) {
    		return response()->api('Invalid URL', 404);
    	}

    	/*$where = [
    		'currency_pair_id' => $pair_id,
    		'direction' => 1, // buy orders
    		'type' => 1, // limit orders
    		'status' => 1, // available for trade
    	];

    	DB::statement("SET sql_mode = '' ");

    	$buyOrders = Trade_order::where($where)
    		->select(DB::raw('id, rate, SUM(tradable_quantity) AS tradable_quantity'))
    		->groupBy('rate')
    		->orderBy('rate', 'desc')
    		->limit($this->orderBookEntries)
    		->get();
    	
    	$where['direction'] = 0;
    	$sellOrders = Trade_order::where($where)
    		->select(DB::raw('id, rate, SUM(tradable_quantity) AS tradable_quantity'))
    		->groupBy('rate')
    		->orderBy('rate', 'desc')
    		->limit($this->orderBookEntries)
    		->get();*/

    	$orderBookData = $this->getOrderBookData($pair_id);
    	
    	return response()->api($orderBookData);
    	// return response()->api(compact('buyOrders', 'sellOrders'));
    }
}
