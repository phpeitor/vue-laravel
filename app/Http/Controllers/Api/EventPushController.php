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

        broadcast(new ExternalEventReceived($data))->toOthers();

        return response()->json(['status' => 'ok']);
    }
}
