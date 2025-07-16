<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
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
      
        return inertia('Template/Create', [

        ]);
    }

    public function store(StoreTemplateRequest $request)
    {
        Template::create($request->validated());
        return redirect()->route('templates.index');
    }
 
}