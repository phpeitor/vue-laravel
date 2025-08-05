<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
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
                $templates = DB::table('message_templates')
                ->where('company_id', $companyId)
                ->where('communication_channel_id', $communicationChannelId)
                ->select('id', 'name', 'category', 'components', 'meta_status')
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($template) {
                    $template->components = json_decode($template->components, true);
                    return $template;
                })
                ->toArray();
            }
        }

        return inertia('Template/Index', [
            'templates' => $templates,
            'companies' => \DB::table('companies')->select('id', 'company_name')->get(),
            'channels' => $channels,
            'selectedCompanyId' => $companyId ? (int) $companyId : null,
            'selectedChannelId' => $communicationChannelId ? (int) $communicationChannelId : null,
            'errorMessage' => $errorMessage,
        ]);
       
    }

    public function create()
    {
        if (!auth()->user()->hasPermissionTo('add template')) {
            return redirect()->route('error.403'); 
        }

        return inertia('Template/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $companyId = $request->query('companyId');
        $communicationChannelId = $request->query('communicationChannelId');

        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'idioma' => 'required|string|max:5',
            'categoria' => 'required|string|max:30',
            'tipo' => 'required|string|max:30',
            'cuerpo' => 'required|string|max:1024',
            'pie_pagina' => 'nullable|string|max:60',
            'header_file' => 'nullable|file|mimes:jpg,jpeg,png,mp4,pdf|max:10240',
        ]);

        preg_match_all('/{{\d+}}/', $validated['cuerpo'], $matches);
        $totalVariables = count(array_unique($matches[0]));

        $examples = [];
        for ($i = 1; $i <= $totalVariables; $i++) {
            $examples[] = "Ejemplo $i";
        }

        $headerComponent = null;

        if ($request->has('tipo_cabecera')) {
            $tipoCabecera = $request->input('tipo_cabecera');

            if ($tipoCabecera === 'texto') {
                $texto = $request->input('texto_encabezado');
                if ($texto) {
                    $headerComponent = [
                        "type" => "HEADER",
                        "format" => "TEXT",
                        "text" => $texto,
                        "example" => [
                            "header_text" => ["Talina"]
                        ]
                    ];
                }
            }

            if ($tipoCabecera === 'multimedia' && $request->hasFile('header_file')) {

                $file = $request->file('header_file');
                $extension = $file->getClientOriginalExtension();
                $format = null;
                $folder = null;

                switch (strtolower($request->input('tipo_multimedia'))) {
                    case 'imagen':
                        $format = 'IMAGE';
                        $folder = 'img';
                        break;
                    case 'video':
                        $format = 'VIDEO';
                        $folder = 'video';
                        break;
                    case 'documento':
                        $format = 'DOCUMENT';
                        $folder = 'pdf';
                        break;
                }

                if ($format && $folder) {
                    $fileName = uniqid('header_') . '.' . $extension;
                    $filePath = "hsm/{$folder}/{$fileName}";
                    $file->move(public_path("hsm/{$folder}"), $fileName);
                    //$url = "https://portal.fortelcorp.com/{$filePath}";
                    $url = "https://portal.fortelcorp.com/metadata/images/flex_fortel/logo_h.png";

                    $headerComponent = [
                        "type" => "HEADER",
                        "format" => $format,
                        "example" => [
                            "header_handle" => [$url]
                        ]
                    ];

                    $absolutePath = public_path("hsm/{$folder}/{$fileName}");
                    \Log::info("Archivo guardado en: {$absolutePath}");
                }
            }
        }

        $components = [];

        if ($headerComponent) {
            $components[] = $headerComponent;
        }

        $components[] = [
            "type" => "BODY",
            "text" => $validated['cuerpo'],
            "example" => [
                "body_text" => [$examples]
            ]
        ];

        if (!empty($validated['pie_pagina'])) {
            $components[] = [
                "type" => "FOOTER",
                "text" => $validated['pie_pagina']
            ];
        }

        $payload = [
            "companyId" => (int)$companyId,
            "communicationChannelId" => (int)$communicationChannelId,
            "templateData" => [
                "name" => $validated['nombre'],
                "language" => $validated['idioma'],
                "category" => strtoupper($validated['categoria']),
                "components" => $components,
            ]
        ];

        $response = Http::withOptions([
            'verify' => false,
        ])->post(env('WHATSAPP_NEW_URL'), $payload);

        $responseData = $response->json();

        /*if ($response->successful() && isset($responseData['success']) && $responseData['success'] === true) {
            return redirect()->route('templates.index')->with('success', 'Plantilla creada exitosamente');
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
        }*/
            
       dd([
            'ruta_local' => $absolutePath ?? null,
            'payload' => $payload
        ]);
    }
 
}