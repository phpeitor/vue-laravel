<?php
namespace App\Http\Controllers\Api;

use App\Events\ExternalEventReceived;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventPushController
{
    public function push(Request $request)
    {
        // Log del payload completo para debugging
        \Log::info('EventPushController::push() recibió webhook', [
            'content_type' => $request->header('Content-Type'),
            'all_data' => $request->all(),
        ]);

        $data = $request->validate([
            'type' => 'required|string',
            'data' => 'required|array',
        ]);

        if (in_array($data['type'], ['message.incoming', 'message.received'], true)) {
            $eventData = $data['data'] ?? [];

            \Log::info('EXTERNAL_EVENT_INCOMING_RECEIVED', [
                'type' => $data['type'],
                'thread_id' => $eventData['thread_id'] ?? null,
                'company_id' => $eventData['company_id'] ?? null,
                'communication_channel_id' => $eventData['communication_channel_id'] ?? null,
                'phone' => $eventData['phone'] ?? null,
                'item_type' => $eventData['item_type'] ?? null,
                'message_create_date' => $eventData['create_date'] ?? null,
            ]);
        }

        // Usamos event() y no broadcast() para que solo se dispare el listener
        // sin emitir ExternalEventReceived por WebSocket (evita el doble broadcast)
        event(new ExternalEventReceived($data));

        return response()->json(['status' => 'ok']);
    }
}
