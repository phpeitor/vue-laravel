<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
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
                $response = Http::withOptions([
                    'verify' => false,
                ])->post(env('WHATSAPP_SYNC_URL'), [
                    'companyId' => $companyId,
                    'communicationChannelId' => $communicationChannelId,
                ]);

                $data = $response->json();

                if ($data['success'] ?? false) {
                    $templates = $data['templateList'] ?? [];
                } else {
                    $errorMessage = $data['message'] ?? 'Error inesperado al cargar las plantillas.';
                }
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
        ]);

        preg_match_all('/{{\d+}}/', $validated['cuerpo'], $matches);
        $totalVariables = count(array_unique($matches[0]));

        $examples = [];
        for ($i = 1; $i <= $totalVariables; $i++) {
            $examples[] = "Ejemplo $i";
        }

        $components = [
            [
                "type" => "BODY",
                "text" => $validated['cuerpo'],
                "example" => [
                    "body_text" => [$examples]
                ]
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

        if ($response->successful()) {
            return redirect()->route('templates.index')->with('success', 'Plantilla creada exitosamente');
        } else {
            return back()->withErrors(['api' => 'Error al crear plantilla: ' . $response->body()]);
        }
    }
 
}