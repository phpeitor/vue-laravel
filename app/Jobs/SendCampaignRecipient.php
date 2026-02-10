<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\WhatsappHsmSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendCampaignRecipient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;          
    public int $backoff = 10;       

    public function __construct(
        public int $recipientId
    ) {}

    public function handle(WhatsappHsmSender $sender): void
    {
        $recipient = CampaignRecipient::find($this->recipientId);

        if (! $recipient || $recipient->status !== 'PENDING') {
            return;
        }

        $recipient->update([
            'status' => 'SENDING',
        ]);

        $result = $sender->sendFromRecipient($recipient);

        if ($result['success']) {
            $recipient->update([
                'status' => 'SENT',
                'provider_message_id' => $result['response']['hsmid'] ?? null,
            ]);

            // ✅ si ya no quedan PENDING/SENDING, finaliza la campaña
            Campaign::finalizeIfDone($recipient->campaign_id);

            return;
        }

        // ❗ si falla, que dispare el failed() del job
        throw new \Exception(
            $result['response']['message'] ?? 'Error al enviar HSM'
        );
    }

    public function failed(Throwable $e): void
    {
        // Intentamos obtener el recipient para saber campaign_id
        $recipient = CampaignRecipient::find($this->recipientId);

        CampaignRecipient::where('id', $this->recipientId)
            ->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);

        // ✅ si ya no quedan PENDING/SENDING, finaliza con FINALLY_FAILED
        if ($recipient) {
            Campaign::finalizeIfDone($recipient->campaign_id);
        }
    }
}
