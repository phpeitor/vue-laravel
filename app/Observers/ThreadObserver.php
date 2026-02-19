<?php

namespace App\Observers;

use App\Events\ThreadCreated;
use App\Models\Thread;

class ThreadObserver
{
    public function created(Thread $thread): void
    {
        broadcast(new ThreadCreated($thread))->toOthers();
    }
}
