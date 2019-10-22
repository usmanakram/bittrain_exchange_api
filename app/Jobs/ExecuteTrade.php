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

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    // public $retryAfter = 3;

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
        /*for ($i=0; $i < 5; $i++) { 
            echo 'TradeOrder ID: ' . $this->tradeOrder->id . "\n";
            sleep(5);
        }*/
        // throw new \Exception('Artificial exception');
        // $this->customLog($this->tradeOrder->toArray());

        // $counterOrder = Trade_order::where([
        //     'currency_pair_id' => $this->tradeOrder->currency_pair_id,
        //     'direction' => ($this->tradeOrder->direction === 0 ? 1 : 0),
        //     'type' => 1
        // ]);

        if ($this->tradeOrder->status === 1) {
            $this->runTradingEngine($this->tradeOrder);
        }
    }

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

    private function updateTradeOrder($order, $tradable_quantity)
    {
        if ($order->tradable_quantity <= $tradable_quantity) {
            $order->tradable_quantity = 0;
            $order->status = 2;
            $order->save();
        } else {
            $order->decrement('tradable_quantity', $tradable_quantity);
        }
    }

    private function updateBalances($order, $currencyPairDetail, $tradable_quantity, $buy_fee, $sell_fee)
    {
        if ($order->direction === 1) { // buy
            // Increase buying(base) currency balance
            Balance::incrementUserBalance($order->user_id, $currencyPairDetail->base_currency_id, ($tradable_quantity - $buy_fee));

            // Decrease selling(quote) currency balances ("total_balance", "in_order_balance")
            Balance::decrementUserBalanceAndInOrderBalance($order->user_id, $currencyPairDetail->quote_currency_id, ($tradable_quantity * $order->rate));
        
        } elseif ($order->direction === 0) { // sell
            // Increase buying(quote) currency balance
            Balance::incrementUserBalance($order->user_id, $currencyPairDetail->quote_currency_id, ($tradable_quantity * $order->rate - $sell_fee));

            // Decrease selling(base) currency balances ("total_balance", "in_order_balance")
            Balance::decrementUserBalanceAndInOrderBalance($order->user_id, $currencyPairDetail->base_currency_id, $tradable_quantity);
        }
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

    private function getCounterOrder(Trade_order $tradeOrder)
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
             * FOR INSTANT/MARKET TRADE
             * Fetch counter order if exist at the same amount. Otherwise, 
             *      lowest rate counter order for buy trade
             *      highest rate counter order for sell trade
             * 
             */
            if ($tradeOrder->direction === 0) {
                $counterOrder = $counterOrder->where('rate', '<=', $tradeOrder->rate)->orderBy('rate', 'desc');
            } else {
                $counterOrder = $counterOrder->where('rate', '>=', $tradeOrder->rate)->orderBy('rate', 'asc');
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

    private function runTradingEngine(Trade_order $tradeOrder)
    {
        // Get oldest placed counter order
        /*$counterOrder = Trade_order::where([
            'currency_pair_id' => $tradeOrder->currency_pair_id,
            'direction' => ($tradeOrder->direction === 0 ? 1 : 0),
            'rate' => $tradeOrder->rate,
            // 'type' => 1,
            'status' => 1,
        ])
        ->whereIn('type', [0, 1])
        // ->whereIn('status', [1, 2])
        ->where('tradable_quantity', '>', 0)
        // ->latest()
        ->oldest()
        // ->latest('updated_at')
        // ->orderBy('updated_at', 'desc')
        ->limit(1)
        // ->get()
        ->first()
        ;*/
        $counterOrder = $this->getCounterOrder($tradeOrder);

        if ($counterOrder) {
            
            // START
            /*$rate = $tradeOrder->rate;
            $tradable_quantity = $tradeOrder->quantity;

            if ($tradeOrder->type === 0 && $tradeOrder->direction === 1 && $counterOrder->rate > $tradeOrder->rate) {
                $rate = $counterOrder->rate;

                $requiredAdditionalBalance = $tradeOrder->quantity * ($rate - $tradeOrder->rate);
                $balance = Balance::getUserBalance($tradeOrder->user_id, $tradeOrder->quote_currency_id);

                $availableBalance = $balance->total_balance - $balance->in_order_balance;
                if ($availableBalance < $requiredAdditionalBalance) {
                    $tradable_quantity = $availableBalance / $rate;
                }
            }*/
            // END
            
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
                // Buy Order Fee (in terms of base currency)
                $buy_fee = $tradable_quantity * ($this->exchangeFee / 100);
                
                // Sell Order Fee (in terms of quote currency)
                $sell_fee = ($tradable_quantity * $tradeOrder->rate) * ($this->exchangeFee / 100);
                
                // Perform trade
                $trade = Trade_transaction::create([
                    'buy_order_id' => $buy_order_id,
                    'sell_order_id' => $sell_order_id,
                    'quantity' => $tradable_quantity,
                    'rate' => $tradeOrder->rate,
                    'buy_fee' => $buy_fee,
                    'sell_fee' => $sell_fee,
                ]);

                // Decrease "tradable_quantity" & update "status" in "trade_orders" table
                $this->updateTradeOrder($tradeOrder, $tradable_quantity);
                $this->updateTradeOrder($counterOrder, $tradable_quantity);

                // Decrease "total_balance" & "in_order_balance" in "balances" table for selling currency
                // Increase "total_balance" in "balances" table for buying currency
                $currencyPairDetail = $tradeOrder->currency_pair;

                $this->updateBalances($tradeOrder, $currencyPairDetail, $tradable_quantity, $buy_fee, $sell_fee);
                $this->updateBalances($counterOrder, $currencyPairDetail, $tradable_quantity, $buy_fee, $sell_fee);

                // Update "latest_prices" table with latest price & volume
                $latest_price = Latest_price::whereCurrencyPairId($tradeOrder->currency_pair_id)->update([
                    'last_price' => $tradeOrder->rate,
                    'volume' => DB::raw('volume + ' . ($tradable_quantity * $tradeOrder->rate))
                ]);

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
             * Pending Task:
             * Broadcast data only if user is logged in. 
             * It will save processing and decrease network traffic
             */

            // Broadcast a message to user for transaction performed
            // $message = ($tradeOrder->direction === 0 ? 'Sell' : 'Buy') . ' Order ' . ($tradeOrder->status === 2 ? 'Partially' : '') . ' Filled';
            $message = ($tradeOrder->direction === 0 ? 'Sell' : 'Buy') . ' Order ' . ($tradeOrder->tradable_quantity > 0 ? 'Partially ' : '') . 'Filled';
            event(new \App\Events\TradeOrderFilled( $message, $tradeOrder->user_id ));
            if ($tradeOrder->user_id !== $counterOrder->user_id) {
                // $message = ($counterOrder->direction === 0 ? 'Sell' : 'Buy') . ' Order ' . ($counterOrder->status === 2 ? 'Partially' : '') . ' Filled';
                $message = ($counterOrder->direction === 0 ? 'Sell' : 'Buy') . ' Order ' . ($counterOrder->tradable_quantity > 0 ? 'Partially ' : '') . 'Filled';
                event(new \App\Events\TradeOrderFilled( $message, $counterOrder->user_id ));
            }

            // Broadcast updated User's OpenOrders
            $openOrdersData = (new \App\Http\Controllers\TradeOrdersController)->getUserOpenOrdersData($tradeOrder->user_id);
            event(new \App\Events\OpenOrdersUpdated( $openOrdersData, $tradeOrder->user_id ));

            // Broadcast updated User's OpenOrders
            $openOrdersData = (new \App\Http\Controllers\TradeOrdersController)->getUserOpenOrdersData($counterOrder->user_id);
            event(new \App\Events\OpenOrdersUpdated( $openOrdersData, $counterOrder->user_id ));
            
            // Broadcast updated Order Book
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
            event(new \App\Events\LiveRates( $prices ));

            if ($objForNextCall) {
                $this->runTradingEngine($objForNextCall);
            }
        }

        // return $tradeOrder;
        // return $counterOrder;
    }    
}
