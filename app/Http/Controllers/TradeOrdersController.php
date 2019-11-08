<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Currency_pair;
use App\Latest_price;
use App\Trade_order;
use App\Trade_transaction;
use App\Balance;

class TradeOrdersController extends Controller
{
	private $fee = 0; // Percentage
	private $orderBookEntries = 100;

    public function buy(Request $request)
    {
    	$request->validate([
            'pair_id' => 'required',
            'type' => 'required',
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

			/*$fee = ($request->quantity * $request->price) * ($this->fee / 100);
			// $fee = $request->quantity * ($this->fee / 100); // need adjustment

			$requiredBalance = ($request->quantity * $request->price) + $fee;*/
			$requiredBalance = $request->quantity * $request->price;

			/**
			 * Warning: It might possible that there will be no record in balances against these currencies for current user
			 * So, we should create records in balances against these (base, quote) currencies for current user
			 * 
			 */
			$userBalance = $balance->quote_currency->balances[0];
			$availableBalance = $userBalance->total_balance - $userBalance->in_order_balance;

			Log::error('################################# START: Balances #################################');
            Log::error('Quantity: ' . $request->quantity);
            Log::error('Price: ' . $request->price);
            Log::error('Required Balance: ' . $requiredBalance);
            Log::error('Total Balance: ' . $userBalance->total_balance);
            Log::error('In Order Balance: ' . $userBalance->in_order_balance);
            Log::error('Available Balance: ' . $availableBalance);
			Log::error('################################## END: Balances ##################################');

			/*if ($availableBalance > $requiredBalance) {
				Log::error('$availableBalance > $requiredBalance');
			} elseif ($availableBalance < $requiredBalance) {
				Log::error('$availableBalance < $requiredBalance');
            	Log::error($availableBalance);
            	Log::error($requiredBalance);
			} elseif ($availableBalance === $requiredBalance) {
				Log::error('$availableBalance === $requiredBalance');
			}
			Log::error('End Testing');
			die;*/

			if ($availableBalance >= $requiredBalance) {
				// Place an order
				$order = Trade_order::create([
					'user_id' => $user_id,
					'currency_pair_id' => $request->pair_id,
					'direction' => 1,
					'quantity' => $request->quantity,
					'rate' => $request->price,
					// 'fee' => $fee,
					// 'fee_currency_id' => $balance->base_currency_id,
					// 'trigger_rate' => NULL,
					'tradable_quantity' => $request->quantity,
					'type' => $request->type,
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

					// Trigger event to add trade in queue for trade execution
					event(new \App\Events\TradeOrderPlaced( $order ));

					// Broadcast OrderBook Data
					$orderBookData = $this->getOrderBookData($request->pair_id);
					event(new \App\Events\OrderBookUpdated( $orderBookData, $request->pair_id ));

					// Broadcast User OpenOrders
					$openOrdersData = $this->getUserOpenOrdersData($user_id);
					event(new \App\Events\OpenOrdersUpdated( $openOrdersData, $user_id ));
					
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
            'type' => 'required',
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


			// $fee = ($request->quantity * $request->price) * ($this->fee / 100);
			// $requiredBalance = ($request->quantity * $request->price) + $fee;
			
			// $userBalance = $balance->quote_currency->balances[0];

			/**
			 * Warning: It might possible that there will be no record in balances against these currencies for current user
			 * So, we should create records in balances against these (base, quote) currencies for current user
			 * 
			 */
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
					// 'fee' => $fee,
					// 'fee_currency_id' => $balance->quote_currency_id,
					// 'trigger_rate' => NULL,
					'tradable_quantity' => $request->quantity,
					'type' => $request->type,
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

					// Trigger event to add trade in queue for trade execution
					event(new \App\Events\TradeOrderPlaced( $order ));

					// Broadcast OrderBook Data
					$orderBookData = $this->getOrderBookData($request->pair_id);
					event(new \App\Events\OrderBookUpdated( $orderBookData, $request->pair_id ));

					// Broadcast User OpenOrders
					$openOrdersData = $this->getUserOpenOrdersData($user_id);
					event(new \App\Events\OpenOrdersUpdated( $openOrdersData, $user_id ));

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

    public function cancelOrder(Request $request, Trade_order $order)
    {
    	$user_id = $request->user()->id;

    	if ( $user_id !== $order->user_id || in_array($order->status, [2, 3]) ) {
    		return response()->api('Resource Not Found', 404); // Not Found
    	}

		DB::beginTransaction();
		try {
			
			// Update order status as canceled
			$order->status = 3;
			$order->save();


			if ($order->direction === 0) {
				$decrement = $order->tradable_quantity;
				$currency_id = $order->currency_pair->base_currency_id;
			} else {
				$decrement = $order->tradable_quantity * $order->rate;
				$currency_id = $order->currency_pair->quote_currency_id;
			}

			// Release user's in order balance
			$balance = Balance::getUserBalance($user_id, $currency_id);
			$balance->decrement('in_order_balance', $decrement);

			DB::commit();

		} catch (\Exception $e) {
			DB::rollBack();
			return response()->api('Some error occurred. Please, try again later', 400); // 400 Bad Request
		}

		return response()->api('Order canceled successfully.');
    }

    public function getOrderBookData($pair_id)
    {
    	$where = [
    		'currency_pair_id' => $pair_id,
    		'direction' => 1, // buy orders
    		// 'type' => 1, // limit orders
    		'status' => 1, // available for trade
    	];

    	DB::statement("SET sql_mode = '' ");

    	$buyOrders = Trade_order::where($where)
    		->whereIn('type', [0, 1]) // instant/market & limit orders
    		->select(DB::raw('id, rate, SUM(tradable_quantity) AS tradable_quantity, rate * SUM(tradable_quantity) AS total'))
    		->groupBy('rate')
    		->orderBy('rate', 'desc')
    		->limit($this->orderBookEntries)
    		->get();
    	
    	$where['direction'] = 0;
    	$sellOrders = Trade_order::where($where)
    		->whereIn('type', [0, 1]) // instant/market & limit orders
    		->select(DB::raw('id, rate, SUM(tradable_quantity) AS tradable_quantity, rate * SUM(tradable_quantity) AS total'))
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

    	$orderBookData = $this->getOrderBookData($pair_id);
    	
    	return response()->api($orderBookData);
    }

    public function getUserTradesBackup(Request $request)
    {
		$user_id = $request->user()->id;

		$trades = Trade_transaction::with([
				'buy_order' => function($query) use ($user_id) {
					$query
						// ->with('currency_pair:id,symbol')
						->with('currency_pair.base_currency:id,symbol')
						->select('id', 'currency_pair_id', 'direction')
						->whereUserId($user_id);
				}, 
				'sell_order' => function($query) use ($user_id) {
					$query
						// ->with('currency_pair:id,symbol')
						->with('currency_pair.base_currency:id,symbol')
						->select('id', 'currency_pair_id', 'direction')
						->whereUserId($user_id);
				}, 
			])
			->whereHas('buy_order', function($query) use ($user_id) {
				$query->whereUserId($user_id);
			})
			->orwhereHas('sell_order', function($query) use ($user_id) {
				$query->whereUserId($user_id);
			})
			->orderBy('id', 'desc')
			->get()
			->makeVisible('created_at');

		$trades->map(function($item) {
			$targetProp = $item->buy_order ? 'buy_order' : 'sell_order';
			// $item['fee'] = $item->buy_order ? $item->buy_fee : $item->sell_fee;

			$item['direction'] = $item[$targetProp]->direction;
			$item['currency_pair_id'] = $item[$targetProp]->currency_pair_id;
			$item['currency_pair_symbol'] = $item[$targetProp]->currency_pair->symbol;
			$item['base_currency_symbol'] = $item[$targetProp]->currency_pair->base_currency->symbol;

			unset($item['buy_order']);
			unset($item['sell_order']);
		});

		return response()->api($trades);
    }

    public function getUserTrades(Request $request)
    {
		
        /*
		page: 1
		rows: 16
		start: 1569870000000
		end: 1570561200000
		baseAsset: ADA
		quoteAsset: BNB
		symbol: 
		direction: BUY
		*/

		$maxHistoryPeriod = strtotime('-3 months');

		$user_id = $request->user()->id;


		$start = strtotime($request->start);
		$start = $start > $maxHistoryPeriod ? $start : $maxHistoryPeriod;

		$start = date('Y-m-d', $start);
		$end = date('Y-m-d', strtotime($request->end));

		$pair_id = $request->pair_id;
		$direction = $request->direction;

		$withArray = [
			'buy_order' => function($query) use ($user_id) {
				$query
					// ->with('currency_pair:id,symbol')
					->with('currency_pair.base_currency:id,symbol')
					->select('id', 'currency_pair_id', 'direction')
					->whereUserId($user_id);
			}, 
			'sell_order' => function($query) use ($user_id) {
				$query
					// ->with('currency_pair:id,symbol')
					->with('currency_pair.base_currency:id,symbol')
					->select('id', 'currency_pair_id', 'direction')
					->whereUserId($user_id);
			}, 
		];

		if ($direction === '0') {
			unset($withArray['buy_order']);
		} elseif ($direction === '1') {
			unset($withArray['sell_order']);
		}

		$trades = Trade_transaction::with($withArray);

		if ($direction === '1') {
			$trades = $trades->whereHas('buy_order', function($query) use ($user_id, $pair_id) {
				$query->where(['user_id' => $user_id, 'currency_pair_id' => $pair_id]);
			});
		} elseif ($direction === '0') {
			$trades = $trades->whereHas('sell_order', function($query) use ($user_id, $pair_id) {
				$query->where(['user_id' => $user_id, 'currency_pair_id' => $pair_id]);
			});
		} else {
			/*$trades = $trades
				->whereHas('buy_order', function($query) use ($user_id) {
					$query->whereUserId($user_id);
				})
				->orwhereHas('sell_order', function($query) use ($user_id) {
					$query->whereUserId($user_id);
				});*/

			$trades = $trades->where(function($query) use ($user_id, $pair_id) {
				$query->whereHas('buy_order', function($query) use ($user_id, $pair_id) {
					$query->where(['user_id' => $user_id, 'currency_pair_id' => $pair_id]);
				})
				->orwhereHas('sell_order', function($query) use ($user_id, $pair_id) {
					$query->where(['user_id' => $user_id, 'currency_pair_id' => $pair_id]);
				});
			});
		}

		if ($start && $end) {
			// $trades = $trades->whereBetween('created_at', [date($start), date($end)]);
			$trades = $trades->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$start, $end]);
		}

		$trades = $trades->orderBy('id', 'desc')
			->get()
			->makeVisible('created_at');
			// ->toSql();

		$trades->map(function($item) use ($user_id, $direction) {
			// $targetProp = $item->buy_order ? 'buy_order' : 'sell_order';
			$targetProp = $direction === '0' ? 'sell_order' : ($direction === '1' ? 'buy_order' : ($item->buy_order ? 'buy_order' : 'sell_order'));
			// $targetProp = $item->buy_order->user_id === $user_id ? 'buy_order' : 'sell_order';
			// $item['fee'] = $item->buy_order ? $item->buy_fee : $item->sell_fee;

			$item['direction'] = $item[$targetProp]->direction;
			$item['currency_pair_id'] = $item[$targetProp]->currency_pair_id;
			$item['currency_pair_symbol'] = $item[$targetProp]->currency_pair->symbol;
			$item['base_currency_symbol'] = $item[$targetProp]->currency_pair->base_currency->symbol;

			unset($item['buy_order']);
			unset($item['sell_order']);
		});

		return response()->api($trades);
    }

    public function getUserOrders(Request $request)
    {
    	$maxHistoryPeriod = strtotime('-3 months');

		$user_id = $request->user()->id;

		$start = strtotime($request->start);
		$start = $start > $maxHistoryPeriod ? $start : $maxHistoryPeriod;

		$start = date('Y-m-d', $start);
		$end = date('Y-m-d', strtotime($request->end));

		$pair_id = $request->pair_id;
		$direction = $request->direction;

		$where = ['user_id' => $user_id];

		if ($pair_id) {
			$where['currency_pair_id'] = $pair_id;
		}
		if ( in_array($direction, ['0', '1']) ) {
			$where['direction'] = $direction;
		}

		$orders = Trade_order::where($where);
		
		if ($start && $end) {
			$orders = $orders->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$start, $end]);
		}

		$orders = $orders
			->with('currency_pair:id,symbol')
			->latest()
			->get()
			->makeVisible('created_at');

		$orders->map(function($item) {
			$item['currency_pair_symbol'] = $item->currency_pair->symbol;
			unset($item->currency_pair);
		});


    	/*$user_id = $request->user()->id;

    	$orders = Trade_order::where([
    			'user_id' => $user_id,
    			// 'status' => 1
    		])
    		->with('currency_pair:id,symbol')
    		->latest()
    		->get()
    		->makeVisible('created_at');

    	$orders->map(function($item) {
			$item['currency_pair_symbol'] = $item->currency_pair->symbol;
			unset($item->currency_pair);
		});*/

		return response()->api($orders);
    }

    public function getUserOpenOrdersData($user_id)
    {
    	$orders = Trade_order::where([
    			'user_id' => $user_id,
    			'status' => 1
    		])
    		->with('currency_pair:id,symbol')
    		->latest()
    		->get()
    		->makeVisible('created_at');

    	$orders->map(function($item) {
			$item['currency_pair_symbol'] = $item->currency_pair->symbol;
			unset($item->currency_pair);
		});

		/**
		 * ->all() is required if we need data (returned by this method) in some other method.
		 * If we skip ->all(), data fetched from other tables is skipped and "created_at" fields is also skipped
		 */
		return $orders->all();
    }

    public function getAllOpenOrdersData()
    {
        $orders = Trade_order::where([
            'status' => 1
        ])
        ->with('currency_pair:id,symbol')
        ->latest()
        ->get()
        ->makeVisible('created_at');
        
        $orders->map(function($item) {
            $item['currency_pair_symbol'] = $item->currency_pair->symbol;
            unset($item->currency_pair);
        });
            
            /**
             * ->all() is required if we need data (returned by this method) in some other method.
             * If we skip ->all(), data fetched from other tables is skipped and "created_at" fields is also skipped
             */
            return $orders->all();
    }
    
    public function getUserOpenOrders(Request $request)
    {
    	$orders = $this->getUserOpenOrdersData($request->user()->id);
		return response()->api($orders);
    }


    private function getCounterOrder(Trade_order $tradeOrder, $instant = false)
    {
        // Get oldest placed counter order
        $counterOrder = Trade_order::where([
            'currency_pair_id' => $tradeOrder->currency_pair_id,
            'direction' => ($tradeOrder->direction === 0 ? 1 : 0),
            // 'rate' => $tradeOrder->rate,
            'status' => 1,
        ]);

        if ($tradeOrder->type === 0) {
        	/**
        	 * Title: For Instant/Market Trade
        	 * Description:
        	 * Fetch counter order if exist at the same amount. Otherwise, 
        	 * 		lowest rate counter order for buy trade
        	 * 		highest rate counter order for sell trade
        	 * 
        	 */
            if ($tradeOrder->direction === 0) {
            	// $counterOrder = $counterOrder->where('rate', '<=', $tradeOrder->rate)->orderBy('rate', 'desc');
            	$counterOrder = $counterOrder->orderBy('rate', 'desc');
            } else {
            	// $counterOrder = $counterOrder->where('rate', '>=', $tradeOrder->rate)->orderBy('rate', 'asc');
            	$counterOrder = $counterOrder->orderBy('rate', 'asc');
            }
            
        } else {
            $counterOrder = $counterOrder->where('rate', $tradeOrder->rate);
        }

        return $counterOrder
            ->whereIn('type', [0, 1])
            ->where('tradable_quantity', '>', 0)
            ->oldest()
            ->limit(1)
            ->first();
    }

    public function tradeEngineTesting(Trade_order $tradeOrder)
    {
    	echo '<pre>';
    	print_r($this->getCounterOrder($tradeOrder)->toArray());
    	echo '</pre>';
    	die;

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
