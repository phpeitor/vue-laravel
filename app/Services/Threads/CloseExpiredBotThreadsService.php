<?php

namespace App\Services\Threads;

use Illuminate\Support\Facades\DB;

class CloseExpiredBotThreadsService
{
    public function execute(): int
    {
        return DB::table('threads')
            ->where('thread_status', 'OPEN')
            ->whereRaw("create_date <= NOW() - interval '24 hour'")
            ->update([
                'thread_status' => 'CLOSED',
                //'bot_duration' => 0,
            ]);
    }
}