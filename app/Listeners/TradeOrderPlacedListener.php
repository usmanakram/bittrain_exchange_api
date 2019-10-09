<?php

namespace App\Listeners;

use App\Events\TradeOrderPlaced;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\ExecuteTrade;

class TradeOrderPlacedListener
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
     * @param  TradeOrderPlaced  $event
     * @return void
     */
    public function handle(TradeOrderPlaced $event)
    {
        dispatch(new ExecuteTrade($event->tradeOrder));
        // dispatch( (new ExecuteTrade($event->tradeOrder))->onQueue('high') );
    }
}
