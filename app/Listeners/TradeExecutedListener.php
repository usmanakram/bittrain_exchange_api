<?php

namespace App\Listeners;

use App\Events\TradeExecuted;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\CalculateExchangeData;

class TradeExecutedListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  TradeExecuted  $event
     * @return void
     */
    public function handle(TradeExecuted $event)
    {
        // dispatch( (new CalculateExchangeData($event->tradeOrder))->onQueue('exchange-stats') );
        CalculateExchangeData::dispatch($event->tradeOrder)->onQueue('exchange-stats');
    }
}
