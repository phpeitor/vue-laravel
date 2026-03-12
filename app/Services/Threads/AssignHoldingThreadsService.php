<?php

namespace App\Services\Threads;

use Illuminate\Support\Facades\DB;

class AssignHoldingThreadsService
{
    public function execute(): array
    {
        $assignedCount = 0;
        $processedCount = 0;
        $details = [];

        DB::transaction(function () use (&$assignedCount, &$processedCount, &$details) {
            $threads = DB::table('threads')
                ->select('id', 'company_id', 'communication_channel_id', 'assigned_agent_id', 'create_date')
                ->where('thread_status', 'OPEN')
                ->where('assigned_agent_id', 2)
                ->whereRaw('COALESCE(room, 0) = 0')
                ->orderBy('create_date', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($threads as $thread) {
                $processedCount++;

                $selectedAgent = DB::table('user_communication_channels as ucc')
                    ->joinSub(
                        DB::table('sessions')
                            ->select('user_id')
                            ->distinct(),
                        'online_users',
                        function ($join) {
                            $join->on('online_users.user_id', '=', 'ucc.user_id');
                        }
                    )
                    ->leftJoin('threads as t', function ($join) {
                        $join->on('t.assigned_agent_id', '=', 'ucc.user_id')
                            ->where('t.thread_status', '=', 'OPEN');
                    })
                    ->where('ucc.company_id', $thread->company_id)
                    ->where('ucc.communication_channel_id', $thread->communication_channel_id)
                    ->whereNotIn('ucc.user_id', [1, 2])
                    ->groupBy('ucc.user_id')
                    ->select(
                        'ucc.user_id',
                        DB::raw('COUNT(t.id) as open_threads_count')
                    )
                    ->orderBy('open_threads_count', 'asc')
                    ->orderBy('ucc.user_id', 'asc')
                    ->first();

                if (! $selectedAgent) {
                    $details[] = [
                        'thread_id' => $thread->id,
                        'assigned_to' => null,
                        'reason' => 'No hay agentes en línea para company/channel',
                    ];
                    continue;
                }

                DB::table('threads')
                    ->where('id', $thread->id)
                    ->update([
                        'assigned_agent_id' => $selectedAgent->user_id,
                    ]);

                $assignedCount++;

                $details[] = [
                    'thread_id' => $thread->id,
                    'assigned_to' => $selectedAgent->user_id,
                    'open_threads_count' => $selectedAgent->open_threads_count,
                ];
            }
        });

        return [
            'processed' => $processedCount,
            'assigned' => $assignedCount,
            'details' => $details,
        ];
    }
}