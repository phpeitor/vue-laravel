<?php

namespace App\Jobs;

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
        \Log::info('SendCampaignRecipient running', [
            'recipient_id' => $this->recipientId,
        ]);
        
        $recipient = CampaignRecipient::find($this->recipientId);

        if (! $recipient || $recipient->status !== 'PENDING') {
            return;
        }

        // Marcar como enviando
        $recipient->update([
            'status' => 'SENDING',
        ]);

        $result = $sender->sendFromRecipient($recipient);

        if ($result['success']) {
            $recipient->update([
                'status' => 'SENT',
                'provider_message_id' => $result['response']['hsmid'] ?? null,
            ]);
        } else {
            throw new \Exception(
                $result['response']['message'] ?? 'Error al enviar HSM'
            );
        }
    }

    public function failed(Throwable $e): void
    {
        CampaignRecipient::where('id', $this->recipientId)
            ->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);
    }
}
