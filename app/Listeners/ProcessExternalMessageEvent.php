<?php

namespace App\Listeners;

use App\Events\ExternalEventReceived;
use App\Events\IncomingMessageNotification;
use Illuminate\Support\Facades\Log;

class ProcessExternalMessageEvent
{
    public function __construct() {}

    public function handle(ExternalEventReceived $event): void
    {
        try {
            $payload = $event->payload;
            $type    = $payload['type'] ?? '';

            if ($type !== 'message.incoming' && $type !== 'message.received') {
                return;
            }

            $data      = $payload['data'] ?? [];
            $companyId = (int) ($data['company_id']               ?? 0);
            $channelId = (int) ($data['communication_channel_id'] ?? 0);

            if (!$companyId || !$channelId) {
                Log::warning('IncomingMessage: falta company_id o communication_channel_id', ['data' => $data]);
                return;
            }

            broadcast(new IncomingMessageNotification([
                'thread_id'                => $data['thread_id']    ?? null,
                'company_id'               => $companyId,
                'communication_channel_id' => $channelId,
                'item_content'             => $data['item_content'] ?? '',
                'item_type'                => $data['item_type']    ?? 'text',
                'name'                     => $data['name']         ?? '',
                'phone'                    => $data['phone']        ?? '',
                'message_create_date'      => $data['create_date']  ?? now()->toISOString(),
                'origin'                   => 'EXTERNAL',
                'enviado_por'              => 'USUARIO',
            ]));

            Log::info('IncomingMessage broadcast enviado', [
                'thread_id'  => $data['thread_id'] ?? null,
                'company_id' => $companyId,
                'channel_id' => $channelId,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error procesando ExternalEventReceived', [
                'exception' => $e->getMessage(),
                'payload'   => $event->payload,
            ]);
        }
    }
}
