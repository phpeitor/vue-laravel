<?php

namespace App\Listeners;

use App\Events\ExternalEventReceived;
use App\Models\Message;
use App\Models\Thread;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessExternalMessageEvent
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     * 
     * El webhook que envía omnichannel cuando llega un mensaje desde WhatsApp
     * contiene la estructura del mensaje que ya fue creado en la BD.
     * Este listener:
     * 1. Valida que el mensaje sea de tipo "message.incoming"
     * 2. Verifica que exista el Message en la BD
     * 3. Carga la relación thread
     * 4. Dispara manualmente el evento MessageCreated si es necesario
     */
    public function handle(ExternalEventReceived $event): void
    {
        try {
            $payload = $event->payload;

            // Log del payload completo para verificar estructura real
            Log::debug('ProcessExternalMessageEvent::handle() procesando evento', [
                'type' => $payload['type'] ?? 'unknown',
                'payload_keys' => array_keys($payload),
                'data_keys' => isset($payload['data']) ? array_keys($payload['data']) : [],
                'full_payload' => $payload, // log completo para debugging
            ]);

            $type = $payload['type'] ?? '';

            // Solo procesar eventos de mensaje entrante
            if ($type !== 'message.incoming' && $type !== 'message.received') {
                Log::info('ExternalEvent ignorado (tipo no es mensaje)', ['type' => $type]);
                return;
            }

            $data = $payload['data'] ?? [];

            // Validación básica
            $threadId = (int) ($data['thread_id'] ?? 0);
            $customerId = (int) ($data['customer_id'] ?? 0);
            $externalId = (string) ($data['external_id'] ?? '');
            $itemType = (string) ($data['item_type'] ?? 'text');
            $itemContent = (string) ($data['item_content'] ?? '');
            $createDate = $data['create_date'] ?? null;

            if (!$threadId || !$customerId) {
                Log::warning('ExternalEvent incompleto (falta thread/customer)', [
                    'thread_id' => $threadId,
                    'customer_id' => $customerId,
                    'external_id' => $externalId,
                ]);
                return;
            }

            // 1) Intentar buscar por external_id si está presente
            $message = null;
            if ($externalId !== '') {
                $message = Message::with('thread')->where('external_id', $externalId)->first();
            }

            // 2) Si no está, intentar buscar por contenido y ventana de tiempo (si create_date viene)
            if (!$message && $createDate) {
                try {
                    $dt = Carbon::parse($createDate);
                    $start = $dt->copy()->subMinutes(2)->toDateTimeString();
                    $end = $dt->copy()->addMinutes(2)->toDateTimeString();

                    $message = Message::with('thread')
                        ->where('thread_id', $threadId)
                        ->where('item_content', $itemContent)
                        ->whereBetween('create_date', [$start, $end])
                        ->first();
                } catch (\Throwable $e) {
                    // parsing failed, seguir con otros métodos
                    Log::debug('No se pudo parsear create_date para búsqueda', ['create_date' => $createDate]);
                }
            }

            // 3) Si sigue sin encontrar, intentar por thread + customer + contenido
            if (!$message) {
                $message = Message::with('thread')
                    ->where('thread_id', $threadId)
                    ->where('customer_id', $customerId)
                    ->where('item_content', $itemContent)
                    ->orderByDesc('id')
                    ->first();
            }

            // 4) Si aún no existe, crear como fallback (solo para evitar perder notificación)
            if (!$message) {
                $message = Message::create([
                    'thread_id' => $threadId,
                    'customer_id' => $customerId,
                    'external_id' => $externalId !== '' ? $externalId : null,
                    'item_type' => $itemType,
                    'item_content' => $itemContent,
                    'origin' => 'IN',
                    'create_date' => $createDate ?? now(),
                ]);

                // eager load thread
                $message->load('thread');

                Log::info('Mensaje creado por listener (fallback)', [
                    'message_id' => $message->id,
                    'thread_id' => $threadId,
                    'external_id' => $externalId,
                ]);
            }

            Log::info('Mensaje encontrado/creado para notificación', [
                'message_id' => $message->id,
                'thread_id' => $threadId,
                'external_id' => $message->external_id,
            ]);

            // Disparar el evento MessageCreated para notificar al frontend
            \App\Events\MessageCreated::dispatch($message);

            Log::info('Evento MessageCreated disparado para notificar al frontend', [
                'message_id' => $message->id,
                'thread_id' => $threadId,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error procesando ExternalEventReceived', [
                'exception' => $e->getMessage(),
                'payload' => $event->payload,
            ]);
        }
    }
}
