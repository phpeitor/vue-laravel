<?php

namespace App\Events;

use App\Models\Message; 
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array|Channel
    {
        // Enviar al canal del thread siempre
        $channels = [
            new PrivateChannel('chat.thread.' . $this->message->thread_id),
        ];

        // Si tenemos acceso a la relación thread, también enviar a company
        if ($this->message->relationLoaded('thread') && $this->message->thread) {
            $channels[] = new PrivateChannel('chat.company.' . $this->message->thread->company_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'thread_id' => $this->message->thread_id,
            'customer_id' => $this->message->customer_id,
            'item_type' => $this->message->item_type,
            'item_content' => $this->message->item_content,
            'message_create_date' => $this->message->create_date,
            'origin' => $this->message->origin,
            'external_id' => $this->message->external_id,
            'enviado_por' => $this->message->origin === 'EXTERNAL' || $this->message->origin === 'USUARIO' ? 'USUARIO' : 'BOT',
        ];
    }
}
