<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TradeHistoryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tradeHistory;
    private $currencyPairId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($tradeHistory, $currencyPairId)
    {
        $this->tradeHistory = $tradeHistory;
        $this->currencyPairId = $currencyPairId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('TradeHistory.' . $this->currencyPairId);
    }
}
