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

        // Usamos event() y no broadcast() para que solo se dispare el listener
        // sin emitir ExternalEventReceived por WebSocket (evita el doble broadcast)
        event(new ExternalEventReceived($data));

        return response()->json(['status' => 'ok']);
    }
}
