<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class IncomingMessageNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public array $data) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('chat.company.' . $this->data['company_id']);
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
