<?php

namespace App\Services;

use App\Models\CampaignRecipient;
use App\Models\Template; // 👈 message_templates
use Illuminate\Support\Facades\DB; // 👈 template_url_laravel
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappHsmSender
{
    public function sendFromRecipient(CampaignRecipient $recipient): array
    {
        $campaign = $recipient->campaign;

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

        /**
         * ✅ HEADER (si existe)
         */
        $templateHeader = $this->buildTemplateHeader(
            templateId: (int) $campaign->template_id,
            companyId: (int) $campaign->company_id,
            channelId: (int) $campaign->communication_channel_id,
        );

        if ($templateHeader) {
            $payload['recipientData']['templateHeader'] = $templateHeader;
        }

        $url = config('services.whatsapp.send_url');

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

    /**
     * Retorna:
     *  - ['type' => 'image'|'video'|'document'|'text', 'variable' => '...']
     *  - o null si no hay header / no hay data suficiente
     */
    private function buildTemplateHeader(int $templateId, int $companyId, int $channelId): ?array
    {
        // Traer components del template
        $tpl = Template::query()
            ->select('id', 'components')
            ->find($templateId);

        if (! $tpl) return null;

        $components = $tpl->components;

        // Por si no tienes cast en el modelo
        if (is_string($components)) {
            $decoded = json_decode($components, true);
            $components = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (! is_array($components)) return null;

        // Buscar HEADER
        $header = collect($components)->first(fn ($c) => strtoupper((string)($c['type'] ?? '')) === 'HEADER');
        if (! $header || ! is_array($header)) return null;

        $format = strtoupper((string)($header['format'] ?? ''));

        // HEADER tipo TEXT => viene del mismo components (ej: "Hello World")
        if ($format === 'TEXT') {
            $text = trim((string)($header['text'] ?? ''));
            if ($text === '') return null;

            return [
                'type' => 'text',
                'variable' => $text,
            ];
        }

        // HEADER tipo archivo => IMAGE / VIDEO / DOCUMENT
        $typeMap = [
            'IMAGE' => 'image',
            'VIDEO' => 'video',
            'DOCUMENT' => 'document',
        ];

        if (! isset($typeMap[$format])) {
            return null; // no soportado o no es header válido
        }

        // Buscar URL en template_url_laravel (primero match por template+company+channel)
        $row = DB::table('template_url_laravel')
            ->select('url')
            ->where('template_id', $templateId)
            ->where('company_id', $companyId)
            ->where('channel_id', $channelId)
            ->orderByDesc('id')
            ->first();

        // fallback: si no existe por company/channel, al menos por template_id
        if (! $row) {
            $row = DB::table('template_url_laravel')
                ->select('url')
                ->where('template_id', $templateId)
                ->orderByDesc('id')
                ->first();
        }

        $url = trim((string)($row->url ?? ''));
        if ($url === '') return null;

        return [
            'type' => $typeMap[$format],
            'variable' => $url,
        ];
    }

}
