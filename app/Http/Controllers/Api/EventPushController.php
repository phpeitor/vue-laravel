<?php
namespace App\Http\Controllers\Api;

use App\Events\ExternalEventReceived;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventPushController
{
    public function push(Request $request)
    {
<<<<<<< HEAD
=======

//        return response()->json(['status' => '666']);
>>>>>>> gitlab/main
        $data = $request->validate([
            'type' => 'required|string',
            'data' => 'required|array',
        ]);
<<<<<<< HEAD

        broadcast(new ExternalEventReceived($data))->toOthers();
=======
//        return response()->json($data);
//        broadcast(new ExternalEventReceived($data))->toOthers();
        broadcast(new ExternalEventReceived($data));
>>>>>>> gitlab/main

        return response()->json(['status' => 'ok']);
    }
}
