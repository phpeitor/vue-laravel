<?php

namespace App\Observers;

use App\Events\MessageCreated;
use App\Models\Message;

class MessageObserver
{
    public function created(Message $message): void
    {
        broadcast(new MessageCreated($message))->toOthers();
        \Log::info('MessageObserver fired', ['id' => $message->id, 'thread_id' => $message->thread_id]);

    }
}
