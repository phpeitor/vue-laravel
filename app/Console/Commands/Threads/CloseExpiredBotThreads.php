<?php

namespace App\Console\Commands\Threads;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\Threads\CloseExpiredBotThreadsService;

class CloseExpiredBotThreads extends Command
{
    protected $signature = 'threads:close-expired-bot';
    protected $description = 'Cierra threads OPEN con bot_duration=1 y más de 24 horas';

    public function __construct(
        protected CloseExpiredBotThreadsService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $updated = $this->service->execute();

        Log::info('threads:close-expired-bot ejecutado', [
            'updated' => $updated,
            'executed_at' => now()->toDateTimeString(),
        ]);

        $this->info("Threads actualizados: {$updated}");

        return self::SUCCESS;
    }
}