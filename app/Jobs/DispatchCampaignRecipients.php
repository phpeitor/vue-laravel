<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Jobs\SendCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchCampaignRecipients implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        \Log::info('DispatchCampaignRecipients START', [
            'campaign_id' => $this->campaignId,
        ]);

        $campaign = Campaign::findOrFail($this->campaignId);

        // ✅ Permitir re-dispatch si está RUNNING
        // ❌ Bloquear si ya terminó
        $allowed = ['READY', 'SCHEDULED', 'RUNNING'];
        $blocked = ['FINALLY', 'FINALLY_FAILED', 'FAILED', 'CANCELLED'];

        if (in_array($campaign->status, $blocked, true) || ! in_array($campaign->status, $allowed, true)) {
            \Log::info('DispatchCampaignRecipients SKIP by status', [
                'campaign_id' => $campaign->id,
                'status' => $campaign->status,
            ]);
            return;
        }

        // 🔒 Marcar RUNNING antes para evitar doble disparo por 2 workers
        if ($campaign->status !== 'RUNNING') {
            $campaign->update(['status' => 'RUNNING']);
        }

        CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'PENDING')
            ->select('id')
            ->chunkById(100, function ($recipients) {
                foreach ($recipients as $recipient) {
                    SendCampaignRecipient::dispatch($recipient->id);
                }
            });

        // ✅ Si ya no quedan pendientes, la finalizeIfDone lo llevará a FINALLY/FINALLY_FAILED
        Campaign::finalizeIfDone($campaign->id);

        \Log::info('DispatchCampaignRecipients END', [
            'campaign_id' => $campaign->id,
        ]);
    }
}
