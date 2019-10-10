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

    /**
     * Only for testing
     * 
     */
    private function customLog($arg) {
        $file = fopen('custom.log', 'a') or die('Unable to open file!');
        fwrite($file, date('Y-m-d H:i:s') . "\n" . print_r($arg, true) . "\n\n");
        fclose($file);
    }

    private function runTradingEngine(Trade_order $tradeOrder)
    {
        // Get oldest placed counter order
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
        ->limit(1)
        // ->get()
        ->first()
        ;

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
                $trade = Trade_transaction::create([
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

                return 'Some error occurred';
            }


            /**
             * Pending Task:
             * Broadcast data only if user is logged in. 
             * It will save processing and decrease network traffic
             */

            // Broadcast a message to user for transaction performed
            $message = ($tradeOrder->direction === 0 ? 'Sell' : 'Buy') . ' Order ' . ($tradeOrder->status === 2 ? 'Partially' : '') . ' Filled';
            event(new \App\Events\TradeOrderFilled( $message, $tradeOrder->user_id ));
            if ($tradeOrder->user_id !== $counterOrder->user_id) {
                $message = ($counterOrder->direction === 0 ? 'Sell' : 'Buy') . ' Order ' . ($counterOrder->status === 2 ? 'Partially' : '') . ' Filled';
                event(new \App\Events\TradeOrderFilled( $message, $counterOrder->user_id ));
            }
            
            // Broadcast updated Order Book
            $orderBookData = (new \App\Http\Controllers\TradeOrdersController)->getOrderBookData($tradeOrder->currency_pair_id);
            event(new \App\Events\OrderBookUpdated( $orderBookData, $tradeOrder->currency_pair_id ));

            if ($objForNextCall) {
                $this->runTradingEngine($objForNextCall);
            }
        }

        // return $tradeOrder;
        // return $counterOrder;
    }

    private function updateTradeOrder($order, $tradable_quantity)
    {
        if ($order->tradable_quantity <= $tradable_quantity) {
            $order->tradable_quantity = 0;
            $order->status = 3;
        } else {
            $order->decrement('tradable_quantity', $tradable_quantity);
            $order->status = 2;
        }

        $order->save();
    }

    private function updateBalances($order, $tradable_quantity, $base_currency_id, $quote_currency_id)
    {
        if ($order->direction === 1) { // buy
            // 100 + 5 = 105
            // Increase buying(base) currency balance
            $partialFee = ($tradable_quantity / $order->quantity) * $order->fee;
            Balance::incrementUserBalance($order->user_id, $base_currency_id, ($tradable_quantity - $partialFee));

            // Decrease selling(quote) currency balances ("total_balance", "in_order_balance")
            $decrement = $tradable_quantity * $order->rate;
            // Balance::decrementUserBalance($order->user_id, $quote_currency_id, $decrement);
            // Balance::decrementUserInOrderBalance($order->user_id, $quote_currency_id, $decrement);
            Balance::decrementUserBalanceAndInOrderBalance($order->user_id, $quote_currency_id, $decrement);
        
        } elseif ($order->direction === 0) { // sell
            
            // Increase buying(quote) currency balance
            $partialFee = ($tradable_quantity / $order->quantity) * $order->fee;
            Balance::incrementUserBalance($order->user_id, $quote_currency_id, ($tradable_quantity * $order->rate - $partialFee));

            // Decrease selling(base) currency balances ("total_balance", "in_order_balance")
            // Balance::decrementUserBalance($order->user_id, $base_currency_id, $tradable_quantity);
            // Balance::decrementUserInOrderBalance($order->user_id, $base_currency_id, $tradable_quantity);
            Balance::decrementUserBalanceAndInOrderBalance($order->user_id, $base_currency_id, $tradable_quantity);

        }
    }
}
