<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatReplyController extends Controller
{
    public function store(Request $request, int $threadId)
    {
        $messageType = strtolower((string) $request->input('messageType', 'text'));
        $isMedia = in_array($messageType, ['image', 'file'], true);

        $rules = [
            'messageType' => ['required', 'string', 'in:text,image,file'],
            'userId' => ['required', 'integer'],
        ];

        if ($isMedia) {
            $rules['file'] = ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:2048'];
            $rules['fileName'] = ['nullable', 'string', 'max:180'];
        } else {
            $rules['message'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);

        Log::info('CHAT_SUBMIT_RECEIVED', [
            'thread_id' => $threadId,
            'message_type' => $messageType,
            'user_id' => (int) ($validated['userId'] ?? 0),
            'has_message' => $messageType === 'text' ? !empty(trim((string) ($validated['message'] ?? ''))) : false,
            'has_file' => $isMedia ? $request->hasFile('file') : false,
            'ip' => $request->ip(),
        ]);

        $data = [
            'messageType' => $messageType,
            'userId' => (int) $validated['userId'],
        ];

        if ($isMedia) {
            $file = $request->file('file');

            $originalName = (string) ($validated['fileName'] ?? ('msg_' . now()->format('Ymd_His') . '_' . uniqid()));
            $nameInfo = pathinfo($originalName);

            $baseName = (string) ($nameInfo['filename'] ?? $originalName);
            $baseName = preg_replace('/[^A-Za-z0-9_-]/', '_', $baseName);
            $baseName = trim((string) $baseName, '_');
            $baseName = $baseName !== '' ? $baseName : ('msg_' . now()->format('Ymd_His') . '_' . uniqid());

            $extension = strtolower((string) ($nameInfo['extension'] ?? ''));
            if ($extension === '') {
                $extension = strtolower((string) $file->getClientOriginalExtension());
            }
            if ($extension === '') {
                $extension = 'jpg';
            }

            $fileName = $baseName . '.' . $extension;

            $isImageFile = str_starts_with((string) $file->getMimeType(), 'image/');
            $storageFolder = $isImageFile ? 'messages_image' : 'messages_file';
            $path = $file->storeAs($storageFolder, $fileName, 'public');
            $fileUrl = url(Storage::url($path));

            $data['message'] = $fileUrl;
            $data['messageType'] = 'file';
        } else {
            $data['message'] = (string) $validated['message'];
        }

        $base = rtrim((string) config('services.chat.thread_base_url'), '/');
        if ($base === '') {
            Log::error('CHAT_THREAD_BASE_URL no configurado', [
                'services.chat.thread_base_url' => config('services.chat.thread_base_url'),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'CHAT_THREAD_BASE_URL no configurado en config/services.php',
            ], 500);
        }

        $url = "{$base}/{$threadId}/reply";

        try {
            // 👇 Si en local tienes cURL error 60 (certificados), habilita verify false SOLO en local
            $http = Http::timeout(20)->acceptJson();

            if (app()->environment('local')) {
                $http = $http->withOptions(['verify' => false]);
            }

            $res = $http->post($url, $data);
        } catch (\Throwable $e) {
            Log::error('Error llamando omnichannel reply', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo conectar al omnichannel',
            ], 502);
        }

        if (!$res->successful()) {
            Log::error('Omnichannel reply no successful', [
                'url' => $url,
                'payload_sent' => $data,
                'status' => $res->status(),
                'body' => $res->body(),
            ]);

            return response()->json([
                'success' => false,
                'status' => $res->status(),
                'error' => $res->json() ?? $res->body(),
            ], 422);
        }

        $payload = $res->json();

        if (!is_array($payload) || !($payload['success'] ?? false) || empty($payload['interaction'])) {
            Log::warning('Respuesta omnichannel inesperada', ['payload' => $payload]);

            return response()->json([
                'success' => false,
                'error' => 'Respuesta inesperada del omnichannel',
                'raw' => $payload,
            ], 422);
        }

        $interaction = $payload['interaction'];
        $messageId = (int) ($interaction['id'] ?? 0);

        // ✅ NO INSERTAR mensaje, SOLO actualizar origin del mensaje ya creado por omnichannel
        if ($messageId > 0) {
            $updated = Message::query()
                ->where('thread_id', $threadId)
                ->where('id', $messageId)
                ->update(['origin' => 'APP']);

            if ($updated === 0) {
                // Si no existe aún por timing, lo logueamos (puedes reintentar en frontend con fetchMessages)
                Log::warning('No se encontró message para actualizar origin', [
                    'thread_id' => $threadId,
                    'message_id' => $messageId,
                ]);
            }

            // opcional: marcar en threads last_outgoing_message_id si lo usas
            Thread::query()
                ->where('id', $threadId)
                ->update(['last_outgoing_message_id' => $messageId]);
        }

        Log::info('CHAT_SUBMIT_SENT', [
            'thread_id' => $threadId,
            'message_type' => $messageType,
            'user_id' => (int) ($validated['userId'] ?? 0),
            'message_id' => $messageId,
            'omnichannel_status' => $res->status(),
        ]);

        return response()->json($payload);
    }
}
