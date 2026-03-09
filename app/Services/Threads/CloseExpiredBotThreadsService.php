<?php

namespace App\Services\Threads;

use Illuminate\Support\Facades\DB;

class CloseExpiredBotThreadsService
{
    public function execute(): int
    {
        return DB::table('threads')
            ->where('thread_status', 'OPEN')
            ->where('create_date', '<=', now()->subHours(24))
            ->update([
                'thread_status' => 'CLOSED',
                //'bot_duration' => 0,
            ]);
    }
}