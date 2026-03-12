<?php

namespace App\Console\Commands\Threads;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\Threads\AssignHoldingThreadsService;

class AssignHoldingThreads extends Command
{
    protected $signature = 'threads:assign-holding';
    protected $description = 'Asigna threads OPEN en holding a agentes online con menor carga';

    public function __construct(
        protected AssignHoldingThreadsService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->execute();

        Log::info('threads:assign-holding ejecutado', [
            'processed' => $result['processed'],
            'assigned' => $result['assigned'],
            'details' => $result['details'],
            'executed_at' => now()->toDateTimeString(),
        ]);

        $this->info("Threads procesados: {$result['processed']}");
        $this->info("Threads asignados: {$result['assigned']}");

        return self::SUCCESS;
    }
}