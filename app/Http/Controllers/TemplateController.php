<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role; 
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Inertia\Inertia;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::denies('viewAny', Template::class)) {
            return Inertia::location(route('error.403'));
        }
        
        $companyId = $request->input('companyId');
        $communicationChannelId = $request->input('communicationChannelId');
        $templates = [];
        $channels = [];
        $errorMessage = null;

        if ($companyId) {
            $channels = \DB::table('communication_channels')
                ->where('company_id', $companyId)
                ->where('status', 'ACTIVO')
                ->select('id', 'channel_name', 'channel_type', 'channel_id')
                ->get()
                ->toArray();

            if ($communicationChannelId) {            
                $templates = DB::table('message_templates as a')
                    ->leftJoin('template_url_laravel as b', 'a.id', '=', 'b.template_id') 
                    ->where('a.company_id', $companyId)
                    ->where('a.communication_channel_id', $communicationChannelId)
                    ->where('a.status_talina', 'true')
                    ->select(
                        'a.id',
                        'a.name',
                        'a.category',
                        'a.components',
                        'a.meta_status',
                        'b.url'
                    )
                    ->orderBy('a.id', 'desc')
                    ->get()
                    ->map(function ($template) {
                        $template->components = json_decode($template->components, true);
                        return $template;
                    })
                    ->toArray();
            }
        }

        // Obtener compañías permitidas según asignaciones del usuario (si las tiene)
        $userId = $request->user()->id;

        $assignments = DB::table('user_communication_channels')
            ->where('user_id', $userId)
            ->get(['company_id', 'communication_channel_id']);

        $companiesQuery = DB::table('companies')->select('id', 'company_name')->orderBy('company_name');

        if ($assignments->isNotEmpty()) {
            $companyIds = $assignments->pluck('company_id')->unique()->values();
            $companiesQuery->whereIn('id', $companyIds);
        }

        $companies = $companiesQuery->get();

        return inertia('Template/Index', [
            'templates' => $templates,
            'companies' => $companies,
            'channels' => $channels,
            'selectedCompanyId' => $companyId ? (int) $companyId : null,
            'selectedChannelId' => $communicationChannelId ? (int) $communicationChannelId : null,
            'errorMessage' => $errorMessage,
        ]);
    }

    public function create()
    {
        /*if (!auth()->user()->hasPermissionTo('add template')) {
            return redirect()->route('error.403'); 
        }

        return inertia('Template/Create', [

        ]);*/
        $this->authorize('create', Template::class);
        return inertia('Template/Create');
    }

    public function store(Request $request)
    {
        $companyId = $request->query('companyId');
        $communicationChannelId = $request->query('communicationChannelId');
        $validated = $this->validateTemplateInput($request);
        $built = $this->buildTemplatePayload($request, $validated, true);
        $payload = $built['payload'];
        $url = $built['header_url'];

        $response = Http::withOptions([
            'verify' => false,
        ])->post(config('services.whatsapp.new_url'), $payload);

        $responseData = $response->json();

        if ($response->successful() && isset($responseData['success']) && $responseData['success'] === true) {

            $hsmId = $responseData['id'] ?? null;

            /*dd([
                'payload_enviado_api'  => $payload,
                'response_data_api'    => $responseData
            ]);*/

            if ($url) {
                DB::table('template_url_laravel')->insert([
                    'name' => $validated['nombre'],
                    'company_id' => $companyId,
                    'channel_id' => $communicationChannelId,
                    'url' => $url,
                    'template_id' => $hsmId,
                ]);
            }

            return redirect()->route('templates.index', [
                'companyId' => $companyId,
                'communicationChannelId' => $communicationChannelId,
            ])->with('success', 'Plantilla creada exitosamente');

        } else {
            $errorMessage = $responseData['message'] ?? 'Error desconocido al crear plantilla';

            if (is_string($errorMessage) && str_starts_with($errorMessage, '{')) {
                $decoded = json_decode($errorMessage, true);
                if (isset($decoded['error']['error_user_msg'])) {
                    $errorMessage = $decoded['error']['error_user_msg'];
                } elseif (isset($decoded['error']['message'])) {
                    $errorMessage = $decoded['error']['message'];
                }
            }

            \Log::error("Error al crear plantilla: " . $errorMessage);
            return back()->withErrors(['api' => 'Error al crear plantilla: ' . $errorMessage]);
        }
            
    }

    public function previewPayload(Request $request): JsonResponse
    {
        $validated = $this->validateTemplateInput($request);
        $built = $this->buildTemplatePayload($request, $validated, false);

        return response()->json([
            'success' => true,
            'preview' => [
                'target_url' => config('services.whatsapp.new_url'),
                'header_url' => $built['header_url'],
                'stored_relative_path' => $built['stored_relative_path'],
                'payload' => $built['payload'],
            ],
        ]);
    }

    public function delete(Request $request, $id)
    {
        $template = DB::table('message_templates')->where('id', $id)->first();

        if (!$template) {
            return redirect()->back()->withErrors(['error' => 'Plantilla no encontrada']);
        }

        DB::table('message_templates')
            ->where('id', $id)
            ->update(['status_talina' => 'false']);

        $companyId = $request->query('companyId');
        $communicationChannelId = $request->query('communicationChannelId');

        return redirect()->route('templates.index', [
            'companyId' => $companyId,
            'communicationChannelId' => $communicationChannelId,
        ])->with('success', 'Plantilla eliminada exitosamente');
        //return response()->json(['message' => 'Plantilla marcada como eliminada'], 200);
    }

    public function testSend(Request $request)
    {
        $validated = $request->validate([
            'companyId' => 'required|integer',
            'communicationChannelId' => 'required|integer',
            'messageTemplateId' => 'required|integer|exists:message_templates,id',
            'recipientData.phone' => 'required|string|size:11', 
            'recipientData.templateBody' => 'required|array',
            'recipientData.templateHeader' => 'nullable|array',
            'recipientData.templateHeader.type' => 'nullable|string|in:image,video,document',
            'recipientData.templateHeader.variable' => 'nullable|string',
        ]);

        $recipientData = $validated['recipientData'];

        if (isset($recipientData['templateHeader'])) {
            $recipientData['templateHeader'] = [
                'type' => strtolower($recipientData['templateHeader']['type']),
                'variable' => $recipientData['templateHeader']['variable'],
            ];
        }

        $payload = [
            'companyId' => $validated['companyId'],
            'communicationChannelId' => $validated['communicationChannelId'],
            'messageTemplateId' => $validated['messageTemplateId'],
            'recipientData' => $recipientData,
        ];

        try {  
            $response = Http::withOptions([
                'verify' => false,
            ])->post(config('services.whatsapp.send_url'), $payload);

            $responseData = $response->json();
            $hsmId = $responseData['hsmid'] ?? null;
            $success = $response->successful() && ($responseData['success'] ?? false) === true;

            /*dd([
            'payload' => $payload,
            'response_status' => $response->status(),
            'response_data' => $responseData
            ]);*/
            
            DB::table('hsm_laravel')->insert([
                'id_template' => $validated['messageTemplateId'],
                'telefono' => $validated['recipientData']['phone'],
                'template_id' => $hsmId,
                'success' => $success ? 1 : 0,
            ]);

            if ($success) {
                return redirect()->route('templates.index', [
                    'companyId' => $validated['companyId'],
                    'communicationChannelId' => $validated['communicationChannelId'],
                ])->with('success', 'Mensaje enviado correctamente.');
            }

            $errorMessage = 'Error desconocido al enviar.';

            if (isset($responseData['message'])) {
                $rawMessage = $responseData['message'];

                if (is_string($rawMessage) && str_starts_with($rawMessage, '{')) {
                    $decoded = json_decode($rawMessage, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        if (isset($decoded['error']['error_user_msg'])) {
                            $errorMessage = $decoded['error']['error_user_msg'];
                        } elseif (isset($decoded['error']['message'])) {
                            $errorMessage = $decoded['error']['message'];
                        } else {
                            $errorMessage = $rawMessage;
                        }
                    } else {
                        $errorMessage = $rawMessage;
                    }
                } else {
                    $errorMessage = $rawMessage;
                }
            }

            return back()->withErrors([
                'toast' => $errorMessage
            ]);


        } catch (\Exception $e) {
            \Log::error('Error al enviar prueba de plantilla: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al conectarse con el API.');
        }
    }

    public function sync(Request $request)
    {
        $validated = $request->validate([
            'companyId' => 'required|integer',
            'communicationChannelId' => 'required|integer',
        ]);

        $payload = [
            'companyId' => $validated['companyId'],
            'communicationChannelId' => $validated['communicationChannelId'],
        ];

        try {
            $response = Http::withOptions([
                'verify' => false,
            ])->post(config('services.whatsapp.sync_url'), $payload);

            $responseData = $response->json();

            if ($response->successful()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Sincronizacion completada correctamente.',
                    ]);
                }

                return redirect()->route('templates.index', [
                    'companyId' => $validated['companyId'],
                    'communicationChannelId' => $validated['communicationChannelId'],
                ])->with('success', 'Sincronizacion completada correctamente.');
            }

            $errorMessage = $responseData['message'] ?? 'No se pudo sincronizar las plantillas.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 422);
            }

            return back()->withErrors([
                'toast' => $errorMessage,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al sincronizar plantillas: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al conectarse con el API de sincronizacion.',
                ], 500);
            }

            return back()->withErrors([
                'toast' => 'Error al conectarse con el API de sincronizacion.',
            ]);
        }
    }

    private function validateTemplateInput(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:100',
            'idioma' => 'required|string|max:5',
            'categoria' => 'required|string|max:30',
            'tipo' => 'required|string|max:30',
            'cuerpo' => 'required|string|max:1024',
            'pie_pagina' => 'nullable|string|max:60',
            'header_file' => 'nullable|file|mimes:jpg,jpeg,png,mp4,pdf|max:5120',
        ]);
    }

    private function buildTemplatePayload(Request $request, array $validated, bool $persistUploadedFile): array
    {
        $companyId = (int) $request->query('companyId');
        $communicationChannelId = (int) $request->query('communicationChannelId');

        preg_match_all('/{{\d+}}/', $validated['cuerpo'], $matches);
        $totalVariables = count(array_unique($matches[0]));

        $headerComponent = null;
        $headerUrl = null;
        $storedRelativePath = null;

        if ($request->has('tipo_cabecera')) {
            $tipoCabecera = $request->input('tipo_cabecera');

            if ($tipoCabecera === 'texto') {
                $texto = $request->input('texto_encabezado');
                if ($texto) {
                    $headerComponent = [
                        'type' => 'HEADER',
                        'format' => 'TEXT',
                        'text' => $texto,
                        'example' => [
                            'header_text' => ['Talina'],
                        ],
                    ];
                }
            }

            if ($tipoCabecera === 'multimedia' && $request->hasFile('header_file')) {
                $headerData = $this->buildMultimediaHeader($request, $persistUploadedFile);
                $headerComponent = $headerData['component'];
                $headerUrl = $headerData['url'];
                $storedRelativePath = $headerData['stored_relative_path'];
            }
        }

        $components = [];

        if ($headerComponent) {
            $components[] = $headerComponent;
        }

        $bodyComponent = [
            'type' => 'BODY',
            'text' => $validated['cuerpo'],
        ];

        if ($totalVariables > 0) {
            $examples = [];
            for ($i = 1; $i <= $totalVariables; $i++) {
                $examples[] = "Ejemplo $i";
            }

            $bodyComponent['example'] = [
                'body_text' => [$examples],
            ];
        }

        $components[] = $bodyComponent;

        $buttonsPayload = $this->buildButtonsPayload($request);

        if (!empty($buttonsPayload)) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttonsPayload,
            ];
        }

        if (!empty($validated['pie_pagina'])) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $validated['pie_pagina'],
            ];
        }

        return [
            'payload' => [
                'companyId' => $companyId,
                'communicationChannelId' => $communicationChannelId,
                'templateData' => [
                    'name' => $validated['nombre'],
                    'language' => $validated['idioma'],
                    'category' => strtoupper($validated['categoria']),
                    'components' => $components,
                ],
            ],
            'header_url' => $headerUrl,
            'stored_relative_path' => $storedRelativePath,
        ];
    }

    private function buildMultimediaHeader(Request $request, bool $persistUploadedFile): array
    {
        $file = $request->file('header_file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());

        $map = [
            'imagen' => ['format' => 'IMAGE', 'folder' => 'img'],
            'video' => ['format' => 'VIDEO', 'folder' => 'video'],
            'documento' => ['format' => 'DOCUMENT', 'folder' => 'pdf'],
        ];

        $type = strtolower((string) $request->input('tipo_multimedia'));
        $selected = $map[$type] ?? null;

        if (!$file || !$selected) {
            return [
                'component' => null,
                'url' => null,
                'stored_relative_path' => null,
            ];
        }

        $fileNamePrefix = $persistUploadedFile ? 'header_' : 'preview_';
        $fileName = $fileNamePrefix . Str::random(12) . '.' . $extension;
        $storedRelativePath = sprintf('hsm/%s/%s', $selected['folder'], $fileName);

        if ($persistUploadedFile) {
            $destination = public_path(sprintf('hsm/%s', $selected['folder']));

            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }

            $file->move($destination, $fileName);
        }

        $url = $this->buildPublicAssetUrl($request, $storedRelativePath);

        return [
            'component' => [
                'type' => 'HEADER',
                'format' => $selected['format'],
                'example' => [
                    'header_handle' => [$url],
                ],
            ],
            'url' => $url,
            'stored_relative_path' => $storedRelativePath,
        ];
    }

    private function buildButtonsPayload(Request $request): array
    {
        $rawButtons = $request->input('botones', []);
        $buttonsPayload = [];
        $errors = [];
        $limits = ['BOTON' => 5, 'URL' => 2, 'TELEFONO' => 1];
        $counts = ['BOTON' => 0, 'URL' => 0, 'TELEFONO' => 0];

        foreach ($rawButtons as $i => $btn) {
            $kind = strtoupper(trim($btn['kind'] ?? ''));
            $text = trim($btn['text'] ?? '');

            if (!in_array($kind, ['BOTON', 'URL', 'TELEFONO'], true)) {
                $errors["botones.$i.kind"] = 'Tipo de botón inválido.';
                continue;
            }

            $counts[$kind]++;
            if ($counts[$kind] > $limits[$kind]) {
                $errors["botones.$i.limit"] = "Se excedió el máximo para $kind.";
                continue;
            }

            if ($text === '' || mb_strlen($text) > 25) {
                $errors["botones.$i.text"] = 'Texto requerido (máximo 25 caracteres).';
                continue;
            }

            if ($kind === 'URL') {
                $btnUrl = trim($btn['url'] ?? '');
                if ($btnUrl === '' || mb_strlen($btnUrl) > 255) {
                    $errors["botones.$i.url"] = 'URL requerida (máximo 255).';
                    continue;
                }
                if (!filter_var($btnUrl, FILTER_VALIDATE_URL)) {
                    $errors["botones.$i.url"] = 'URL inválida.';
                    continue;
                }

                $buttonsPayload[] = [
                    'type' => 'URL',
                    'text' => $text,
                    'url' => $btnUrl,
                ];
                continue;
            }

            if ($kind === 'TELEFONO') {
                $phone = preg_replace('/\D+/', '', (string) ($btn['phone'] ?? ''));
                if ($phone === '' || strlen($phone) !== 11) {
                    $errors["botones.$i.phone"] = 'Teléfono requerido de 11 dígitos.';
                    continue;
                }

                $buttonsPayload[] = [
                    'type' => 'PHONE_NUMBER',
                    'text' => $text,
                    'phone_number' => $phone,
                ];
                continue;
            }

            $buttonsPayload[] = [
                'type' => 'QUICK_REPLY',
                'text' => $text,
            ];
        }

        if (!empty($errors)) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        return $buttonsPayload;
    }

    private function buildPublicAssetUrl(Request $request, string $relativePath): string
    {
        return rtrim($request->root(), '/') . '/' . ltrim($relativePath, '/');
    }

}