<?php

namespace App\Listeners;

use App\Events\TradeOrderPlaced;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\ExecuteTrade;

class TradeOrderPlacedListener
{
    // Reference: https://laravel.com/docs/5.8/events
    /**
     * The name of the connection the job should be sent to.
     *
     * @var string|null
     */
    // public $connection = 'sqs';

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    // public $queue = 'listeners';

    /**
     * The time (seconds) before the job should be processed.
     *
     * @var int
     */
    // public $delay = 60;

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
        /**
         * Dispatching To A Particular Connection
         * 
         * If you are working with multiple queue connections, you may specify which connection to push a job to.
         * To specify the connection, use the onConnection method when dispatching the job:
         */
        // ExecuteTrade::dispatch($event->tradeOrder)->onConnection('sqs');

        /*ExecuteTrade::dispatch($event->tradeOrder)
              ->onConnection('sqs')
              ->onQueue('processing');*/

        
        // ExecuteTrade::dispatch($event->tradeOrder)->onQueue('high');
        // dispatch( (new ExecuteTrade($event->tradeOrder))->onQueue('high') );
        dispatch(new ExecuteTrade($event->tradeOrder));
    }

    /**
     * Determine whether the listener should be queued.
     *
     * @param  \App\Events\OrderPlaced  $event
     * @return bool
     */
    /*public function shouldQueue(OrderPlaced $event)
    {
        return $event->order->subtotal >= 5000;
    }*/

    /**
     * Handle a job failure.
     *
     * @param  \App\Events\OrderShipped  $event
     * @param  \Exception  $exception
     * @return void
     */
    /*public function failed(OrderShipped $event, $exception)
    {
        //
    }*/
}
