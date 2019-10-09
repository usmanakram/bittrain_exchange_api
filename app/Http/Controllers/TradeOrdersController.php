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
			// $fee = $request->quantity * ($this->fee / 100); // need adjustment

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

					// Trigger event to add trade in queue for trade execution
					event(new \App\Events\TradeOrderPlaced( $order ));
					
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

					// Trigger event to add trade in queue for trade execution
					event(new \App\Events\TradeOrderPlaced( $order ));

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

    public function getOrderBookData($pair_id)
    {
    	$where = [
    		'currency_pair_id' => $pair_id,
    		'direction' => 1, // buy orders
    		'type' => 1, // limit orders
    		// 'status' => 1, // available for trade
    	];

    	DB::statement("SET sql_mode = '' ");

    	$buyOrders = Trade_order::where($where)
    		->whereIn('status', [1, 2])
    		->select(DB::raw('id, rate, SUM(tradable_quantity) AS tradable_quantity'))
    		->groupBy('rate')
    		->orderBy('rate', 'desc')
    		->limit($this->orderBookEntries)
    		->get();
    	
    	$where['direction'] = 0;
    	$sellOrders = Trade_order::where($where)
    		->whereIn('status', [1, 2])
    		->select(DB::raw('id, rate, SUM(tradable_quantity) AS tradable_quantity'))
    		->groupBy('rate')
    		->orderBy('rate', 'asc')
    		->limit($this->orderBookEntries)
    		->get();

    	// $sellOrders = $sellOrders->sortByDesc('rate');
    	// $sellOrders = $sellOrders->values()->all();
    	
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

    public function tradeEngineTesting(Trade_order $tradeOrder)
    {
		$counterOrder = Trade_order::where([
			'currency_pair_id' => $tradeOrder->currency_pair_id,
			'direction' => ($tradeOrder->direction === 0 ? 1 : 0),
			'rate' => $tradeOrder->rate,
			// 'type' => 1,
			// 'status' => 1,
		])
		->whereIn('type', [0, 1])
		->whereIn('status', [1, 2])
		->where('tradable_quantity', '>', 0)
		// ->latest()
		->oldest()
		// ->latest('updated_at')
		// ->orderBy('updated_at', 'desc')
		// ->get()
		->limit(1)
		->first()
		;

		// return $tradeOrder;
		return $counterOrder;


		if ($counterOrder) {
			if ($counterOrder->tradable_quantity <= $tradeOrder->tradable_quantity) {
				$tradable_quantity = $counterOrder->tradable_quantity;
				
				if ($counterOrder->tradable_quantity === $tradeOrder->tradable_quantity) {
					$objForNextCall = null;
				} else {
					$objForNextCall = $tradeOrder;
				}
			} else {
				$tradable_quantity = $tradeOrder->tradable_quantity;
				$objForNextCall = $counterOrder;
			}

			if ($tradeOrder->direction === 1) {
				$buy_order_id = $tradeOrder->id;
				$sell_order_id = $counterOrder->id;
			} else {
				$buy_order_id = $counterOrder->id;
				$sell_order_id = $tradeOrder->id;
			}

			DB::beginTransaction();

			try {
				
				// Perform trade
				$trade = \App\Trade_transaction::create([
					'buy_order_id' => $buy_order_id,
					'sell_order_id' => $sell_order_id,
					'quantity' => $tradable_quantity,
					'rate' => $tradeOrder->rate,
				]);

				// Decrease "tradable_quantity" & update "status" in "trade_orders" table
				$this->updateTradeOrder($tradeOrder, $tradable_quantity);
				$this->updateTradeOrder($counterOrder, $tradable_quantity);

				// Decrease "total_balance" & "in_order_balance" in "balances" table for selling currency
				// Increase "total_balance" in "balances" table for buying currency
				$currencyPairDetail = $tradeOrder->currency_pair;
				$base_currency_id = $currencyPairDetail->base_currency_id;
				$quote_currency_id = $currencyPairDetail->quote_currency_id;

				$this->updateBalances($tradeOrder, $tradable_quantity, $base_currency_id, $quote_currency_id);
				$this->updateBalances($counterOrder, $tradable_quantity, $base_currency_id, $quote_currency_id);

				DB::commit();

			} catch (\Exception $e) {
				DB::rollBack();
			
				$error_msg = "ERROR at \nLine: " . $e->getLine() . "\nFILE: " . $e->getFile() . "\nActual File: " . __FILE__ . "\nMessage: ".$e->getMessage();
	            Log::error($error_msg);

				// Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
				Log::channel('slack')->critical(
					"Trade Execution: \n" . 
					// "*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
					// "*Data:* " . json_encode($request->all()) . "\n" . 
					"*Error:* " . $error_msg
				);
			}


			// Broadcast a message to user for transaction performed

			if ($objForNextCall) {
				$this->tradeEngineTesting($objForNextCall);
			}
		}

    	// return $tradeOrder;
    	return $counterOrder;
    }

    private function updateTradeOrder($order, $tradable_quantity)
    {
    	if ($order->tradable_quantity <= $tradable_quantity) {
    		$order->tradable_quantity = 0;
    		$order->status = 3;
    	} else {
    		$order->decrement('tradable_quantity', $tradable_quantity);
    	}

    	$order->save();
    }

    private function updateBalances($order, $tradable_quantity, $base_currency_id, $quote_currency_id)
    {
    	if ($order->direction === 1) { // buy
    		// 100 + 5 = 105
    		// Increase buying(base) currency balance
    		$fee = ($tradable_quantity / $order->quantity) * $order->fee;
    		Balance::incrementUserBalance($order->user_id, $base_currency_id, ($tradable_quantity - $fee));

    		// Decrease selling(quote) currency balances ("total_balance", "in_order_balance")
    		$decrement = $tradable_quantity * $order->rate;
    		// Balance::decrementUserBalance($order->user_id, $quote_currency_id, $decrement);
    		// Balance::decrementUserInOrderBalance($order->user_id, $quote_currency_id, $decrement);
    		Balance::decrementUserBalanceAndInOrderBalance($order->user_id, $quote_currency_id, $decrement);
    	
    	} elseif ($order->direction === 0) { // sell
    		
    		// Increase buying(quote) currency balance
    		$fee = ($tradable_quantity / $order->quantity) * $order->fee;
    		Balance::incrementUserBalance($order->user_id, $quote_currency_id, ($tradable_quantity * $order->rate - $fee));

    		// Decrease selling(base) currency balances ("total_balance", "in_order_balance")
    		// Balance::decrementUserBalance($order->user_id, $base_currency_id, $tradable_quantity);
    		// Balance::decrementUserInOrderBalance($order->user_id, $base_currency_id, $tradable_quantity);
    		Balance::decrementUserBalanceAndInOrderBalance($order->user_id, $base_currency_id, $tradable_quantity);

    	}
    }
}
