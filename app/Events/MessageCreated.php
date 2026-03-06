<?php

namespace App\Events;

use App\Models\Message; 
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Declaración explícita con default a nivel de clase para que la
    // deserialización de jobs viejos (que no incluyen estas propiedades)
    // no lance "Typed property must not be accessed before initialization".
    public ?int $payloadCompanyId = null;
    public ?int $payloadChannelId = null;

    public function __construct(
        public Message $message,
        ?int $payloadCompanyId = null,
        ?int $payloadChannelId = null,
    ) {
        $this->payloadCompanyId = $payloadCompanyId;
        $this->payloadChannelId = $payloadChannelId;
    }

    private function resolvedCompanyId(): ?int
    {
        if ($this->payloadCompanyId) return $this->payloadCompanyId;
        if ($this->message->relationLoaded('thread') && $this->message->thread) {
            return $this->message->thread->company_id;
        }
        return $this->message->thread()->value('company_id');
    }

    public function broadcastOn(): array|Channel
    {
        $channels = [
            new PrivateChannel('chat.thread.' . $this->message->thread_id),
        ];

        $companyId = $this->resolvedCompanyId();
        if ($companyId) {
            $channels[] = new PrivateChannel('chat.company.' . $companyId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        $thread = $this->message->relationLoaded('thread') ? $this->message->thread : $this->message->thread()->first();

        return [
            'message_id'               => $this->message->id,
            'thread_id'                => $this->message->thread_id,
            'customer_id'              => $this->message->customer_id,
            'item_type'                => $this->message->item_type,
            'item_content'             => $this->message->item_content,
            'message_create_date'      => $this->message->create_date,
            'origin'                   => $this->message->origin,
            'external_id'              => $this->message->external_id,
            'company_id'               => $this->payloadCompanyId ?? $thread?->company_id,
            'communication_channel_id' => $this->payloadChannelId  ?? $thread?->communication_channel_id,
            'enviado_por'              => $this->message->origin === 'EXTERNAL' || $this->message->origin === 'USUARIO' ? 'USUARIO' : 'BOT',
        ];
    }
}
