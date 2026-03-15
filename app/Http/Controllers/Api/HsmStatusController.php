<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HsmStatusController extends Controller
{
    public function show(string $providerMessageId): JsonResponse
    {
        $base = rtrim((string) config('services.hsm.base_url'), '/');

        if ($base === '') {
            Log::error('HSM_STATUS_BASE_URL no configurado', [
                'services.hsm.base_url' => config('services.hsm.base_url'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'HSM_STATUS_BASE_URL no configurado en config/services.php',
            ], 500);
        }

        $url = sprintf('%s/hsm/%s/status', $base, rawurlencode($providerMessageId));

        try {
            $http = Http::timeout(20)->acceptJson();

            if (app()->environment('local')) {
                $http = $http->withOptions(['verify' => false]);
            }

            $res = $http->get($url);
        } catch (\Throwable $e) {
            Log::error('Error llamando HSM status', [
                'url' => $url,
                'provider_message_id' => $providerMessageId,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar al servicio de estado HSM.',
            ], 502);
        }

        $payload = $res->json();

        if (!$res->successful()) {
            Log::warning('HSM status no successful', [
                'url' => $url,
                'provider_message_id' => $providerMessageId,
                'status' => $res->status(),
                'body' => $res->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => is_array($payload) ? ($payload['message'] ?? 'No se pudo consultar el status.') : 'No se pudo consultar el status.',
            ], 422);
        }

        if (!is_array($payload)) {
            Log::warning('HSM status payload invalido', [
                'url' => $url,
                'provider_message_id' => $providerMessageId,
                'body' => $res->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Respuesta invalida del servicio de estado HSM.',
            ], 422);
        }

        return response()->json($payload);
    }
}
