<?php

namespace App\Services;

use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\Http;

class WhatsappHsmSender
{
    public function sendFromRecipient(CampaignRecipient $recipient): array
    {
        $campaign = $recipient->campaign;

        // Variables del Excel → array plano
        $variables = collect($recipient->variables)
            ->sortKeys()
            ->values()
            ->toArray();

        $payload = [
            'companyId' => $campaign->company_id,
            'communicationChannelId' => $campaign->communication_channel_id,
            'messageTemplateId' => $campaign->template_id,
            'recipientData' => [
                'phone' => $recipient->phone,
                'templateBody' => $variables,
            ],
        ];

        $response = Http::withOptions([
            'verify' => false,
        ])->post(env('WHATSAPP_SEND_URL'), $payload);

        return [
            'payload' => $payload,
            'success' => $response->successful(),
            'response' => $response->json(),
            'status' => $response->status(),
        ];
    }
}
