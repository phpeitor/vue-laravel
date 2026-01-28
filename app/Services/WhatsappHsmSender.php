<?php

namespace App\Services;

use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $url = env('WHATSAPP_SEND_URL');

        Log::info('WhatsApp HSM SEND - Request', [
            'recipient_id' => $recipient->id,
            'campaign_id' => $campaign->id,
            'url' => $url,
            'payload' => $payload,
        ]);

        try {
            $response = Http::withOptions([
                'verify' => false,
            ])->post($url, $payload);

            // 🔎 LOG: response
            Log::info('WhatsApp HSM SEND - Response', [
                'recipient_id' => $recipient->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'payload' => $payload,
                'success' => $response->successful(),
                'response' => $response->json(),
                'status' => $response->status(),
            ];

        } catch (\Throwable $e) {
            // 🔥 LOG: exception
            Log::error('WhatsApp HSM SEND - Exception', [
                'recipient_id' => $recipient->id,
                'url' => $url,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
