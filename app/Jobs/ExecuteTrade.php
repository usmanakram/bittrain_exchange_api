<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Trade_order;
use App\Trade_transaction;
use App\Balance;
use App\Latest_price;
use Exception;

class ExecuteTrade implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $tradeOrder;

    // Reference: https://laravel.com/docs/5.8/queues
    /**
     * The queue connection that should handle the job.
     *
     * @var string
     */
    // public $connection = 'sqs';

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    // public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    // public $retryAfter = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    // public $timeout = 120;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    private $exchangeFee = 0; // Percentage

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Trade_order $tradeOrder)
    {
        $this->tradeOrder = $tradeOrder;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // throw new \Exception('Artificial exception');
        // $this->customLog($this->tradeOrder->toArray());

        if ($this->tradeOrder->status === 1) {
            $this->runTradingEngine($this->tradeOrder);
        }
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    /*public function retryUntil()
    {
        return now()->addSeconds(5);
    }*/

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        // Send user notification of failure, etc...

        // Slack Log (emergency, alert, critical, error, warning, notice, info and debug)
        Log::channel('slack')->debug(
            "ExecuteTrade Job: \n" . 
            // "*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
            "*Data:* " . json_encode($this->tradeOrder->toArray()) . "\n" . 
            "*Error:* " . $exception->getMessage()
        );
    }

    /**
     * Only for testing
     * 
     */
    private function customLog($arg) {
        $file = fopen('custom.log', 'a') or die('Unable to open file!');
        fwrite($file, date('Y-m-d H:i:s') . "\n" . print_r($arg, true) . "\n\n");
        fclose($file);
    }

    private function getCounterOrder(Trade_order $tradeOrder, $marketRate)
    {
        /**
         * If trade order is limit order and placed wisely
         * Ignore it right now and wait for counter instant or mistakenly placed order
         * 
         */
        if (
                $tradeOrder->type === 1 && 
                (
                    ($tradeOrder->direction === 0 && $tradeOrder->rate > $marketRate) || 
                    ($tradeOrder->direction === 1 && $tradeOrder->rate < $marketRate)
                )
        ) {
            return null;
        }

        // Get (oldest placed) counter order
        $counterOrder = Trade_order::where([
            'currency_pair_id' => $tradeOrder->currency_pair_id,
            'direction' => ($tradeOrder->direction === 0 ? 1 : 0),
            // 'rate' => $tradeOrder->rate,
            'status' => 1,
        ]);

        if ($tradeOrder->type === 1) {
            if ($tradeOrder->direction === 0) {
                $counterOrder = $counterOrder->where('rate', '>=', $tradeOrder->rate);
            } else {
                $counterOrder = $counterOrder->where('rate', '<=', $tradeOrder->rate);
            }
        }

        if ($tradeOrder->direction === 0) {
            $counterOrder = $counterOrder->orderBy('rate', 'desc');
        } else {
            $counterOrder = $counterOrder->orderBy('rate', 'asc');
        }

        return $counterOrder
            ->whereIn('type', [0, 1])
            ->where('tradable_quantity', '>', 0)
            ->oldest()
            ->limit(1)
            ->first();
    }

    private function getTradeRate($tradeOrder, $counterOrder, $marketRate)
    {
        $rate = null;

        if ($tradeOrder->type === 0) {
            if ($tradeOrder->direction === 0) {
                // 2
                $rate = ($counterOrder->rate < $marketRate) ? $counterOrder->rate : $marketRate;
            } else {
                // 1
                $rate = ($counterOrder->rate > $marketRate) ? $counterOrder->rate : $marketRate;
            }
        } elseif ($tradeOrder->type === 1) {
            if ($tradeOrder->direction === 0) {

                // if user is selling below market rate (by mistake)
                // if ($tradeOrder->rate < $marketRate) {
                if ($tradeOrder->rate <= $marketRate) {
                    if ($counterOrder->rate >= $marketRate) {
                        // 4.1
                        $rate = $marketRate;
                    } elseif ($counterOrder->rate >= $tradeOrder->rate) {
                        // 4.2
                        $rate = $counterOrder->rate;
                    } else {
                        // 4.3
                        // ignore
                    }
                }
            } else {

                // if user is buying above market rate (by mistake)
                // if ($tradeOrder->rate > $marketRate) {
                if ($tradeOrder->rate >= $marketRate) {
                    if ($counterOrder->rate <= $marketRate) {
                        // 3.1
                        $rate = $marketRate;
                    } elseif ($counterOrder->rate <= $tradeOrder->rate) {
                        // 3.2
                        $rate = $counterOrder->rate;
                    } else {
                        // 3.3
                        // ignore
                    }
                }
            }
        }

        return $rate;
    }

    private function getObjectForNextCallAndTradableQuantity($tradeOrder, $counterOrder, $tradeOrderQuantity, $counterOrderQuantity)
    {
        if ($tradeOrderQuantity === $counterOrderQuantity) {
            $objForNextCall = null;
            $tradable_quantity = $tradeOrderQuantity;
        } elseif ($tradeOrderQuantity > $counterOrderQuantity) {
            $objForNextCall = $tradeOrder;
            $tradable_quantity = $counterOrderQuantity;
        } else {
            $objForNextCall = $counterOrder;
            $tradable_quantity = $tradeOrderQuantity;
        }
        return compact('objForNextCall', 'tradable_quantity');
    }

    private function updateTradeOrder($order, $tradable_quantity, $objForNextCall)
    {
        if ($order->tradable_quantity <= $tradable_quantity || is_null($objForNextCall) || $order->id !== $objForNextCall->id) {
            // $order->tradable_quantity = 0;
            if ($order->tradable_quantity <= $tradable_quantity) {
                $order->tradable_quantity = 0;
            } else {
                $order->tradable_quantity = $order->tradable_quantity - $tradable_quantity;
            }
            $order->status = 2;
            $order->save();
        } else {
            $order->decrement('tradable_quantity', $tradable_quantity);
        }
    }

    private function updateBalances($order, $currencyPairDetail, $tradable_quantity, $rate, $buyFee, $sellFee, $objForNextCall)
    {
        if ($order->direction === 1) { // buy
            // Increase buying(base) currency balance
            Balance::incrementUserBalance($order->user_id, $currencyPairDetail->base_currency_id, ($tradable_quantity - $buyFee));

            // Decrease selling(quote) currency balances ("total_balance", "in_order_balance")
            if (is_null($objForNextCall) || $order->id !== $objForNextCall->id) {
                $in_order_balance_decrement = $order->tradable_quantity * $order->rate;
            } else {
                $in_order_balance_decrement = $tradable_quantity * $order->rate;
            }
            Balance::decrementUserBalanceAndInOrderBalance($order->user_id, $currencyPairDetail->quote_currency_id, ($tradable_quantity * $rate), $in_order_balance_decrement);
        
        } elseif ($order->direction === 0) { // sell
            // Increase buying(quote) currency balance
            Balance::incrementUserBalance($order->user_id, $currencyPairDetail->quote_currency_id, ($tradable_quantity * $rate - $sellFee));

            // Decrease selling(base) currency balances ("total_balance", "in_order_balance")
            Balance::decrementUserBalanceAndInOrderBalance($order->user_id, $currencyPairDetail->base_currency_id, $tradable_quantity);
        }
    }

    private function activateConditionalOrders(Trade_order $tradeOrder, $marketRate)
    {
        $orders = Trade_order::where([
            'currency_pair_id' => $tradeOrder->currency_pair_id,
            'type' => 2, // stop_limit
        ])
        ->with('condition')
        ->whereHas('condition', function($query) use ($marketRate) {
            $query
                ->where('lower_trigger_rate', '>=', $marketRate)
                ->orWhere('upper_trigger_rate', '<=', $marketRate);
        })
        ->get();

        foreach ($orders as $order) {
            $order->status = 1;
            $order->save();
            $order->condition->status = 2;
            $order->condition->save();
        }
    }

    private function runTradingEngine(Trade_order $tradeOrder)
    {
        // Get Market Rate
        $lastTrade = Trade_transaction::latest()->first();

        $marketRate = $lastTrade ? $lastTrade->rate : 0.01;

        $counterOrder = $this->getCounterOrder($tradeOrder, $marketRate);

        if ($counterOrder) {

            // START
            // Log::error('################################# Rate Before #################################');
            // Log::error($marketRate);
            // Log::error(var_dump($marketRate));
            $rate = $this->getTradeRate($tradeOrder, $counterOrder, $marketRate);
            // Log::error('################################# Rate After #################################');
            // Log::error($rate);
            // Log::error(var_dump($rate));
            // Log::error('tradeOrderID: ' . $tradeOrder->id);
            // Log::error('counterOrderID: ' . $counterOrder->id);

            if ( !$rate ) {
                return 'Suitable order not found';
            }

            // $rate = $tradeOrder->rate;
            // $tradable_quantity = $tradeOrder->tradable_quantity;
            $tradable_quantity = $tradeOrder->tradable_quantity < $counterOrder->tradable_quantity ? $tradeOrder->tradable_quantity : $counterOrder->tradable_quantity;

            $currencyPairDetail = $tradeOrder->currency_pair;


            // START
            extract($this->getObjectForNextCallAndTradableQuantity($tradeOrder, $counterOrder, $tradeOrder->tradable_quantity, $counterOrder->tradable_quantity));
            // END


            // if ($tradeOrder->type === 0 && $tradeOrder->rate !== $counterOrder->rate) {
            //     $rate = $counterOrder->rate;
                
                if ($tradeOrder->direction === 1) {
                    $buy_order_id = $tradeOrder->id;
                    $sell_order_id = $counterOrder->id;

                    // if rate has been increased
                    if ($rate > $tradeOrder->rate) {
                        $requiredAdditionalBalance = $tradeOrder->tradable_quantity * ($rate - $tradeOrder->rate);

                        $balance = Balance::getUserBalance($tradeOrder->user_id, $currencyPairDetail->quote_currency_id);

                        $availableBalance = $balance->total_balance - $balance->in_order_balance;

                        // if user doesn't have sufficient balance
                        if ($availableBalance < $requiredAdditionalBalance) {
                            // Decrease "tradable_quantity"
                            $tradable_quantity = ($availableBalance + $tradeOrder->rate * $tradeOrder->tradable_quantity) / $rate;

                            // START
                            extract($this->getObjectForNextCallAndTradableQuantity($tradeOrder, $counterOrder, $tradable_quantity, $counterOrder->tradable_quantity));
                            // END
                        }
                    }
                } else {
                    $buy_order_id = $counterOrder->id;
                    $sell_order_id = $tradeOrder->id;

                    // if rate has been increased
                    if ($rate > $counterOrder->rate) {
                        $requiredAdditionalBalance = $counterOrder->tradable_quantity * ($rate - $counterOrder->rate);

                        $balance = Balance::getUserBalance($counterOrder->user_id, $currencyPairDetail->quote_currency_id);

                        $availableBalance = $balance->total_balance - $balance->in_order_balance;

                        // if user doesn't have sufficient balance
                        if ($availableBalance < $requiredAdditionalBalance) {
                            // Decrease "tradable_quantity"
                            $tradable_quantity = ($availableBalance + $counterOrder->rate * $counterOrder->tradable_quantity) / $rate;

                            // START
                            extract($this->getObjectForNextCallAndTradableQuantity($tradeOrder, $counterOrder, $tradeOrder->tradable_quantity, $tradable_quantity));
                            // END
                        }
                    }
                }
            // }
            // END

            /**
             * Need to consider another scenario.
             * If $tradeOrder is MarketBuy order and $counterOrder's rate is less than $tradeOrder's rate
             * 
             */
            
            /** Another issue:
             * If order is executed at some rate other than that user's mentiond, we need to readjust user's balances (in_order_balance & total_balance)
             * 
             */
            /*$rate = $tradeOrder->rate;
            $tradable_quantity = $tradeOrder->tradable_quantity;

            if ($tradeOrder->type === 0 && $tradeOrder->rate !== $counterOrder->rate) {
                $rate = $counterOrder->rate;
                
                if ($tradeOrder->direction === 1) {
                    // if rate has been increased
                    if ($rate > $tradeOrder->rate) {
                        $requiredAdditionalBalance = $tradeOrder->tradable_quantity * ($rate - $tradeOrder->rate);

                        $balance = Balance::getUserBalance($tradeOrder->user_id, $tradeOrder->currency_pair->quote_currency_id);

                        $availableBalance = $balance->total_balance - $balance->in_order_balance;

                        // if user doesn't have sufficient balance
                        if ($availableBalance < $requiredAdditionalBalance) {
                            // Decrease "tradable_quantity"
                            $tradable_quantity = ($availableBalance + $tradeOrder->rate * $tradeOrder->tradable_quantity) / $rate;
                        }
                    }
                }
            }*/

            /*if ($tradeOrder->direction === 0) {
                $buy_order_id = $counterOrder->id;
                $sell_order_id = $tradeOrder->id;

                if ($tradeOrder->tradable_quantity === $tradable_quantity) {
                    $objForNextCall = null;
                } elseif ($tradeOrder->tradable_quantity > $tradable_quantity) {
                    $objForNextCall = $tradeOrder;
                } else {
                    $objForNextCall = $counterOrder;
                }
            } else {
                $buy_order_id = $tradeOrder->id;
                $sell_order_id = $counterOrder->id;

                if ($counterOrder->tradable_quantity === $tradable_quantity) {
                    $objForNextCall = null;
                } elseif ($counterOrder->tradable_quantity > $tradable_quantity) {
                    $objForNextCall = $counterOrder;
                } else {
                    $objForNextCall = $counterOrder;
                }
            }*/

            DB::beginTransaction();

            try {
                // Buy Order Fee (in terms of base currency)
                $buy_fee = $tradable_quantity * ($this->exchangeFee / 100);
                
                // Sell Order Fee (in terms of quote currency)
                // $sell_fee = ($tradable_quantity * $tradeOrder->rate) * ($this->exchangeFee / 100);
                $sell_fee = ($tradable_quantity * $rate) * ($this->exchangeFee / 100);
                
                // Perform trade
                $trade = Trade_transaction::create([
                    'buy_order_id' => $buy_order_id,
                    'sell_order_id' => $sell_order_id,
                    'quantity' => $tradable_quantity,
                    // 'rate' => $tradeOrder->rate,
                    'rate' => $rate,
                    'buy_fee' => $buy_fee,
                    'sell_fee' => $sell_fee,
                ]);

                /*// Decrease "tradable_quantity" & update "status" in "trade_orders" table
                $this->updateTradeOrder($tradeOrder, $tradable_quantity, $objForNextCall);
                $this->updateTradeOrder($counterOrder, $tradable_quantity, $objForNextCall);*/

                // Decrease "total_balance" & "in_order_balance" in "balances" table for selling currency
                // Increase "total_balance" in "balances" table for buying currency
                $currencyPairDetail = $tradeOrder->currency_pair;

                $this->updateBalances($tradeOrder, $currencyPairDetail, $tradable_quantity, $rate, $buy_fee, $sell_fee, $objForNextCall);
                $this->updateBalances($counterOrder, $currencyPairDetail, $tradable_quantity, $rate, $buy_fee, $sell_fee, $objForNextCall);

                // Decrease "tradable_quantity" & update "status" in "trade_orders" table
                $this->updateTradeOrder($tradeOrder, $tradable_quantity, $objForNextCall);
                $this->updateTradeOrder($counterOrder, $tradable_quantity, $objForNextCall);

                // Update "latest_prices" table with latest price & volume
                $latest_price = Latest_price::whereCurrencyPairId($tradeOrder->currency_pair_id)->update([
                    // 'last_price' => $tradeOrder->rate,
                    // 'volume' => DB::raw('volume + ' . ($tradable_quantity * $tradeOrder->rate))
                    'last_price' => $rate,
                    'volume' => DB::raw('volume + ' . ($tradable_quantity * $rate))
                ]);

                // Trigger conditional orders
                $this->activateConditionalOrders($tradeOrder, $rate);

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

                return 'Some error occurred';
            }


            /**
             * Pending Tasks:
             * 1) Broadcast data only if user is logged in. 
             *      It will save processing and decrease network traffic
             * 2) All queries and broadcasting their result should be done in separate Job to optimize trading engine
             */
            event(new \App\Events\TradeExecuted( $tradeOrder ));
            // \App\Jobs\CalculateExchangeData::dispatch($tradeOrder)->onQueue('exchange-stats');

            // Broadcast a message to user for transaction performed
            // $message = ($tradeOrder->direction === 0 ? 'Sell' : 'Buy') . ' Order ' . ($tradeOrder->status === 2 ? 'Partially' : '') . ' Filled';
            $message = ($tradeOrder->direction === 0 ? 'Sell' : 'Buy') . ' Order ' . ($tradeOrder->tradable_quantity > 0 ? 'Partially ' : '') . 'Filled';
            event(new \App\Events\TradeOrderFilled( $message, $tradeOrder->user_id ));
            // if ($tradeOrder->user_id !== $counterOrder->user_id) {
                // $message = ($counterOrder->direction === 0 ? 'Sell' : 'Buy') . ' Order ' . ($counterOrder->status === 2 ? 'Partially' : '') . ' Filled';
                $message = ($counterOrder->direction === 0 ? 'Sell' : 'Buy') . ' Order ' . ($counterOrder->tradable_quantity > 0 ? 'Partially ' : '') . 'Filled';
                event(new \App\Events\TradeOrderFilled( $message, $counterOrder->user_id ));
            // }

            // Broadcast updated User's OpenOrders
            $openOrdersData = (new \App\Http\Controllers\TradeOrdersController)->getUserOpenOrdersData($tradeOrder->user_id);
            event(new \App\Events\OpenOrdersUpdated( $openOrdersData, $tradeOrder->user_id ));

            // Broadcast updated User's OpenOrders
            $openOrdersData = (new \App\Http\Controllers\TradeOrdersController)->getUserOpenOrdersData($counterOrder->user_id);
            event(new \App\Events\OpenOrdersUpdated( $openOrdersData, $counterOrder->user_id ));
            
            /*// Broadcast updated Order Book
            $orderBookData = (new \App\Http\Controllers\TradeOrdersController)->getOrderBookData($tradeOrder->currency_pair_id);
            event(new \App\Events\OrderBookUpdated( $orderBookData, $tradeOrder->currency_pair_id ));

            // Broadcast updated Trade History
            $tradeHistory = (new \App\Http\Controllers\TradeTransactionsController)->getTradeTransactionsData($tradeOrder->currency_pair_id);
            event(new \App\Events\TradeHistoryUpdated( $tradeHistory, $tradeOrder->currency_pair_id ));

            // Broadcast CandleStick chart History
            $candleChartData = (new \App\Http\Controllers\TradeTransactionsController)->getTradeHistoryForChartData($tradeOrder->currency_pair_id);
            event(new \App\Events\CandleStickGraphUpdated( $candleChartData, $tradeOrder->currency_pair_id ));

            // Broadcast latest prices
            $prices = (new \App\Http\Controllers\CurrencyPairsController)->latestPricesData();
            event(new \App\Events\LiveRates( $prices ));*/

            if ($objForNextCall) {
                $this->runTradingEngine($objForNextCall);
            }
        }
    }
}
