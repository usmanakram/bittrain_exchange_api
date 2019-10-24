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

class CalculateExchangeData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $tradeOrder;

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
        // Broadcast updated Order Book
        $orderBookData = (new \App\Http\Controllers\TradeOrdersController)->getOrderBookData($this->tradeOrder->currency_pair_id);
        event(new \App\Events\OrderBookUpdated( $orderBookData, $this->tradeOrder->currency_pair_id ));

        // Broadcast updated Trade History
        $tradeHistory = (new \App\Http\Controllers\TradeTransactionsController)->getTradeTransactionsData($this->tradeOrder->currency_pair_id);
        event(new \App\Events\TradeHistoryUpdated( $tradeHistory, $this->tradeOrder->currency_pair_id ));

        // Broadcast CandleStick chart History
        $candleChartData = (new \App\Http\Controllers\TradeTransactionsController)->getTradeHistoryForChartData($this->tradeOrder->currency_pair_id);
        event(new \App\Events\CandleStickGraphUpdated( $candleChartData, $this->tradeOrder->currency_pair_id ));

        // Broadcast latest prices
        $prices = (new \App\Http\Controllers\CurrencyPairsController)->latestPricesData();
        event(new \App\Events\LiveRates( $prices ));
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
            "CalculateExchangeData Job: \n" . 
            // "*Host:* " . $_SERVER['HTTP_HOST'] . "\n" . 
            "*Data:* " . json_encode($this->tradeOrder->toArray()) . "\n" . 
            "*Error:* " . $exception->getMessage()
        );
    }
}
