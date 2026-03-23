<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Campaign;
use App\Models\CampaignUpload;
use App\Models\CampaignLog;
use App\Models\CampaignRecipient;
use App\Models\Company;
use App\Models\CommunicationChannel;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role; 
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use App\Jobs\ProcessCampaignUpload;
use App\Jobs\SendCampaignRecipient;
use Illuminate\Validation\ValidationException;
use App\Services\WhatsappHsmSender;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        $selectedCampaignId = $request->integer('campaign_id');
        $recipientsPage = $request->integer('recipients_page', 1);

        $perPage = 6;
        $recipientsPerPage = 15;

        $campaigns = Campaign::query()
            ->leftJoin('companies as cc', 'campaigns.company_id', '=', 'cc.id')
            ->leftJoin('communication_channels as ch', 'campaigns.communication_channel_id', '=', 'ch.id')
            ->leftJoin('message_templates as mt', 'campaigns.template_id', '=', 'mt.id')
            ->select([
                'campaigns.*',
                'cc.company_name as company_name',
                'ch.channel_name as channel_name',
                'mt.name as template_name',
            ])
            ->withCount([
                'logs',
                'recipients',
                'recipients as sent_count' => fn ($q) => $q->where('status', 'SENT'),
                'recipients as failed_count' => fn ($q) => $q->where('status', 'FAILED'),
                'recipients as pending_count' => fn ($q) => $q->where('status', 'PENDING'),
            ])
            ->orderByDesc('campaigns.id')
            ->paginate($perPage)
            ->withQueryString(); 

        if (! $selectedCampaignId) {
            $selectedCampaignId = $campaigns->getCollection()->first()?->id;
        }

        $campaigns->appends(['campaign_id' => $selectedCampaignId]);
        $logs = collect();
        $recipients = new \Illuminate\Pagination\Paginator(collect(), $recipientsPerPage, $recipientsPage);

        if ($selectedCampaignId) {
            $logs = CampaignLog::query()
                ->where('campaign_id', $selectedCampaignId)
                ->orderByDesc('id')
                ->get()
                ->map(function ($log) {
                    $meta = $log->meta;

                    if (is_string($meta)) {
                        $decoded = json_decode($meta, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $meta = $decoded;
                        }
                    }

                    if (($log->type ?? null) === 'UPLOAD' && is_array($meta) && !empty($meta['file'])) {
                        $meta['download_url'] = Storage::disk('public')->url($meta['file']);
                    }

                    $log->meta = $meta;
                    return $log;
                });

            $recipients = CampaignRecipient::query()
                ->where('campaign_id', $selectedCampaignId)
                ->orderByDesc('id')
                ->paginate($recipientsPerPage, ['*'], 'recipients_page', $recipientsPage)
                ->appends(['campaign_id' => $selectedCampaignId]);
        }

        return Inertia::render('Campaign/Index', [
            'campaigns' => $campaigns,
            'campaign_logs' => $logs,
            'campaign_recipients' => $recipients,
            'selectedCampaignId' => $selectedCampaignId,
        ]);
    
    }

    public function create()
    {
        $this->authorize('create', Campaign::class);

        $userId = request()->user()->id;
        $assignments = DB::table('user_communication_channels')
            ->where('user_id', $userId)
            ->get(['company_id', 'communication_channel_id']);

        $companiesQuery = Company::select('id', 'company_name')->orderBy('company_name');
        if ($assignments->isNotEmpty()) {
            $companyIds = $assignments->pluck('company_id')->unique()->values();
            $companiesQuery->whereIn('id', $companyIds);
        }

        return Inertia::render('Campaign/Create', [
            'companies' => $companiesQuery->get(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign deleted successfully.');
    }

    public function channels(Company $company)
    {
        return response()->json(
            $company->communicationChannels()
                ->where('channel_type', 'whatsapp-meta')
                ->where('status', 'ACTIVO')
                ->select('id', 'channel_name')
                ->orderBy('id')
                ->get()
        );
    }

    public function templates(Company $company, CommunicationChannel $channel)
    {
        return Template::query()
            ->where('company_id', $company->id)
            ->where('communication_channel_id', $channel->id)
            ->where('status_talina', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    public function templatePreview(Template $template)
    {
        return [
            'components' => $template->components,
        ];
    }

    public function store(Request $request)
    {
        $this->authorize('create', Campaign::class);

        $data = $request->validate([
            'nombre'        => ['required', 'string', 'max:100'],
            'descripcion'   => ['nullable', 'string', 'max:100'],
            'tipo'          => ['required', 'in:Manual,Programada'],
            'fecha_inicio'  => ['required', 'date'],
            'fecha_fin'     => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'compania'      => ['required', 'integer', 'exists:companies,id'],
            'canal'         => ['required', 'integer', 'exists:communication_channels,id'],
            'template'      => ['required', 'integer', 'exists:message_templates,id'],
            'file'          => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'hora_inicio' => [
                                'nullable',
                                'required_if:tipo,Programada',
                                'date_format:H:i',
                            ],
            
        ]);

        if ($data['tipo'] === 'Programada') {
            $startAt = Carbon::parse(
                $data['fecha_inicio'].' '.$data['hora_inicio']
            );

            if ($startAt->lessThan(now()->startOfMinute())) {
                throw ValidationException::withMessages([
                    'hora_inicio' => 'No puedes programar una campaña en este horario',
                ]);
            }
        }

        $channel = CommunicationChannel::query()
            ->where('id', $data['canal'])
            ->where('company_id', $data['compania'])
            ->first();

        if (! $channel) {
            throw ValidationException::withMessages([
                'canal' => 'El canal no pertenece a la compañía seleccionada.',
            ]);
        }

        $template = Template::query()
            ->where('id', $data['template'])
            ->where('company_id', $data['compania'])
            ->where('communication_channel_id', $data['canal'])
            ->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'template' => 'La plantilla no pertenece al canal seleccionado.',
            ]);
        }

        $this->validateExcelHeaderAgainstTemplate($request, $template);

        DB::beginTransaction();

        try {
            $campaign = Campaign::create([
                'name'                     => $data['nombre'],
                'description'              => $data['descripcion'],
                'company_id'               => $data['compania'],
                'communication_channel_id' => $data['canal'],
                'template_id'              => $data['template'],
                'start_date'               => $data['fecha_inicio'],
                'end_date'                 => $data['fecha_fin'],
                'type'                     => $data['tipo'],
                'status'                   => 'DRAFT',
                'start_time'               => $data['hora_inicio'] ?? null,
            ]);

            $file = $request->file('file');
            $path = $file->store('campaign_uploads', 'public');

            $upload = CampaignUpload::create([
                'campaign_id' => $campaign->id,
                'file_name'   => $file->getClientOriginalName(),
                'file_path'   => $path,
            ]);

           if ($campaign->type === 'Programada') {
                $campaign->update([
                    'status' => 'SCHEDULED',
                ]);
            } else {
                $campaign->update([
                    'status' => 'UPLOADED',
                ]);
            }

            CampaignLog::create([
                'campaign_id' => $campaign->id,
                'type'        => 'UPLOAD',
                'message'     => 'Archivo Excel cargado correctamente.',
                'meta'        => [
                    'upload_id' => $upload->id,
                    'file'      => $path,
                ],
            ]);

            DB::commit();

            try {
                ProcessCampaignUpload::dispatch($upload->id);
            } catch (\Throwable $e) {
                report($e);

                $campaign->update([
                    'status' => 'FAILED',
                ]);

                CampaignLog::create([
                    'campaign_id' => $campaign->id,
                    'type' => 'FAILED',
                    'message' => 'Error durante el procesamiento del Excel.',
                    'meta' => [
                        'error' => $e->getMessage(),
                    ],
                ]);
            }

            return redirect()
                ->route('campaigns.index')
                ->with('success', 'Campaña creada y archivo cargado correctamente.');

        } catch (\Throwable $e) {
            DB::rollBack();

            report($e);

            throw ValidationException::withMessages([
                'file' => 'Ocurrió un error al crear la campaña. Intenta nuevamente.',
            ]);
        }
    }

    private function extractVariableCountFromTemplate(Template $template): int
    {
        $components = $template->components ?? [];
        $body = collect($components)->first(function ($c) {
            return data_get($c, 'type') === 'BODY';
        });

        $text = data_get($body, 'text');

        if (!is_string($text) || $text === '') {
            return 0;
        }

        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $matches);

        if (empty($matches[1])) {
            return 0;
        }

        $unique = array_unique($matches[1]);
        return count($unique);
    }

    private function validateExcelHeaderAgainstTemplate(Request $request, Template $template): void
    {
        $file = $request->file('file');
        $variableCount = $this->extractVariableCountFromTemplate($template);

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
        } catch (SpreadsheetReaderException $e) {
            throw ValidationException::withMessages([
                'file' => 'No se pudo leer el archivo Excel. Verifica que sea .xlsx o .xls válido.',
            ]);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $headerRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? [];

        $headerRow = array_values(array_map(function ($v) {
            return is_string($v) ? trim(mb_strtolower($v)) : '';
        }, $headerRow));

        if (count($headerRow) < 1) {
            throw ValidationException::withMessages([
                'file' => 'El Excel debe tener encabezados en la fila 1.',
            ]);
        }

        if (($headerRow[0] ?? '') !== 'telefono') {
            throw ValidationException::withMessages([
                'file' => 'La primera columna del Excel debe llamarse "telefono".',
            ]);
        }

        $expectedColumns = 1 + max(0, (int)$variableCount);

        if (count($headerRow) !== $expectedColumns) {
            if ($variableCount > 0) {
                throw ValidationException::withMessages([
                    'file' => "El Excel debe tener {$expectedColumns} columnas (telefono + {$variableCount} variables).",
                ]);
            }

            throw ValidationException::withMessages([
                'file' => 'El Excel debe tener 1 columna: "telefono". (La plantilla no tiene variables)',
            ]);
        }

        $highestDataRow = (int) $sheet->getHighestDataRow();
        $records = 0;

        for ($row = 2; $row <= $highestDataRow; $row++) {
            $rowValues = $sheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, true, false)[0] ?? [];

            $hasData = collect($rowValues)->contains(function ($value) {
                if ($value === null) {
                    return false;
                }

                return trim((string) $value) !== '';
            });

            if (! $hasData) {
                continue;
            }

            $records++;

            if ($records > 1500) {
                throw ValidationException::withMessages([
                    'file' => 'El archivo Excel no puede tener más de 1500 registros.',
                ]);
            }

            $phone = preg_replace('/\D+/', '', (string) ($rowValues[0] ?? ''));

            if (! $this->isValidPhone($phone)) {
                throw ValidationException::withMessages([
                    'file' => "El teléfono en la fila {$row} no es válido. Debe empezar con 519 y tener 11 dígitos (ejemplo: 51942890820).",
                ]);
            }
        }

        if ($records === 0) {
            throw ValidationException::withMessages([
                'file' => 'El Excel no contiene registros para procesar.',
            ]);
        }
    }

    private function isValidPhone(string $phone): bool{
        return (bool) preg_match('/^519\d{8}$/', $phone);
    }

    public function testSendFromRecipient(CampaignRecipient $recipient, WhatsappHsmSender $sender){
        $result = $sender->sendFromRecipient($recipient);
        dd($result);
    }

    public function exportRecipients(Campaign $campaign)
    {
        $this->authorize('viewAny', Campaign::class);

        $rows = DB::select('select * from public.fn_campaign_recipients_export(?)', [$campaign->id]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $colLetter = function (int $colIndex): string {
            $s = '';
            while ($colIndex > 0) {
                $colIndex--;
                $s = chr(65 + ($colIndex % 26)) . $s;
                $colIndex = intdiv($colIndex, 26);
            }
            return $s;
        };
        
        $headers = [
            'id', 'name', 'description', 'company_name', 'channel_name',
            'start_date', 'end_date', 'start_time', 'status', 'type',
            'phone', 'variables', 'status_envio', 'last_datetime', 'last_status',
        ];

        foreach ($headers as $i => $h) {
            $cell = $colLetter($i + 1) . '1';
            $sheet->setCellValue($cell, $h);
        }

        // Write data
        $r = 2;
        foreach ($rows as $row) {
            $rowArr = (array) $row;

            // variables puede venir como objeto/array => stringify
            if (isset($rowArr['variables']) && is_array($rowArr['variables'])) {
                $rowArr['variables'] = json_encode($rowArr['variables'], JSON_UNESCAPED_UNICODE);
            } elseif (isset($rowArr['variables']) && is_object($rowArr['variables'])) {
                $rowArr['variables'] = json_encode($rowArr['variables'], JSON_UNESCAPED_UNICODE);
            }

            foreach ($headers as $i => $key) {
                $cell = $colLetter($i + 1) . $r;
                $sheet->setCellValue($cell, $rowArr[$key] ?? '');
            }
            $r++;
        }

        $filename = "campaign_{$campaign->id}_recipients.xlsx";

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}