<?php

namespace App\Events;

use App\Models\Thread; 
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Thread $thread) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('chat.company.' . $this->thread->company_id);
    }

    public function broadcastAs(): string
    {
        return 'thread.created';
    }

    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->thread->thread_id,
            'thread_status' => $this->thread->thread_status,
            'name' => $this->thread->name,
            'phone' => $this->thread->phone,
            'last_message' => $this->thread->last_message,
            'last_at' => $this->thread->last_at,
            'company_id' => $this->thread->company_id,
        ];
    }
}
