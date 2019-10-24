<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class LiveRates implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $rates;

    /**
     * The name of the queue on which to place the event.
     *
     * @var string
     */
    public $broadcastQueue = 'exchange-stats';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($rates)
    {
        $this->rates = $rates;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // return new PrivateChannel('channel-name');
        return new Channel('live');
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    /*public function broadcastAs()
    {
        return 'server.created';
    }*/

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    /*public function broadcastWith()
    {
        return ['id' => $this->user->id];
    }*/

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    /*public function broadcastWhen()
    {
        return $this->value > 100;
    }*/
}
