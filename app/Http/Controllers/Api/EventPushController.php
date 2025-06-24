<?php
namespace App\Http\Controllers\Api;

use App\Events\ExternalEventReceived;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventPushController
{
    public function push(Request $request)
    {

//        return response()->json(['status' => '666']);
        $data = $request->validate([
            'type' => 'required|string',
            'data' => 'required|array',
        ]);
//        return response()->json($data);
//        broadcast(new ExternalEventReceived($data))->toOthers();
        broadcast(new ExternalEventReceived($data));

        return response()->json(['status' => 'ok']);
    }
}
