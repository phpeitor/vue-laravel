<?php

namespace App\Services\Threads;

use Illuminate\Support\Facades\DB;

class AssignHoldingThreadsService
{
    private const MAX_OPEN_THREADS_PER_AGENT = 3;

    public function execute(): array
    {
        $assignedCount = 0;
        $processedCount = 0;
        $details = [];

        DB::transaction(function () use (&$assignedCount, &$processedCount, &$details) {
            $threads = DB::table('threads')
                ->select(
                    'id',
                    'company_id',
                    'communication_channel_id',
                    'assigned_agent_id',
                    'create_date',
                    'room'
                )
                ->where('thread_status', 'OPEN')
                ->where('assigned_agent_id', 2)
                ->orderBy('create_date', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($threads as $thread) {
                $processedCount++;

                $candidateQuery = DB::table('room as r')
                    ->join('user_room as ur', 'ur.room_id', '=', 'r.id')
                    ->joinSub(
                        DB::table('sessions')
                            ->select('user_id')
                            ->distinct(),
                        'online_users',
                        function ($join) {
                            $join->on('online_users.user_id', '=', 'ur.user_id');
                        }
                    )
                    ->leftJoin('threads as t', function ($join) {
                        $join->on('t.assigned_agent_id', '=', 'ur.user_id')
                            ->where('t.thread_status', '=', 'OPEN');
                    })
                    ->where('r.company_id', $thread->company_id)
                    ->where('r.communication_channel_id', $thread->communication_channel_id)
                    ->whereNotIn('ur.user_id', [1, 2]);

                if ((int) $thread->room > 0) {
                    $candidateQuery->where('r.id', $thread->room);
                }

                $selectedAgent = $candidateQuery
                    ->groupBy('ur.user_id', 'r.id', 'r.nombre')
                    ->havingRaw('COUNT(t.id) < ?', [self::MAX_OPEN_THREADS_PER_AGENT])
                    ->select(
                        'ur.user_id',
                        'r.id as room_id',
                        'r.nombre as room_name',
                        DB::raw('COUNT(t.id) as open_threads_count')
                    )
                    ->orderBy('open_threads_count', 'asc')
                    ->orderBy('ur.user_id', 'asc')
                    ->first();

                if (! $selectedAgent) {
                    $details[] = [
                        'thread_id' => $thread->id,
                        'thread_room' => $thread->room,
                        'assigned_to' => null,
                        'reason' => 'No hay usuarios online disponibles o todos alcanzaron el limite de threads OPEN',
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
                    'thread_room' => $thread->room,
                    'assigned_to' => $selectedAgent->user_id,
                    'room_id' => $selectedAgent->room_id,
                    'room_name' => $selectedAgent->room_name,
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