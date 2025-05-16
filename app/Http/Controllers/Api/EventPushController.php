<?php
namespace App\Http\Controllers\Api;

use App\Events\ExternalEventReceived;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventPushController
{
    public function push(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string',
            'data' => 'required|array',
        ]);

        broadcast(new ExternalEventReceived($data))->toOthers();

        return response()->json(['status' => 'ok']);
    }
}
