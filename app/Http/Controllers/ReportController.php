<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class ReportController extends Controller
{
    public function interactions()
    {
        return Inertia::render('Reports/Interactions');
    }

    public function threads()
    {
        return Inertia::render('Reports/Threads');
    }

    private function interactionsBaseQuery(array $validated)
    {
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        return DB::table('threads as t')
            ->leftJoin('messages as m', 't.id', '=', 'm.thread_id')
            ->leftJoin('users_laravel as u', 't.assigned_agent_id', '=', 'u.id')
            ->leftJoin('companies as c', 'c.id', '=', 't.company_id')
            ->leftJoin('communication_channels as d', 'd.id', '=', 't.communication_channel_id')
            ->leftJoin('customers as cc1', 'cc1.id', '=', 't.customer_id')
            ->leftJoin('customer_channels as cc2', 'cc2.id', '=', 't.customer_channel_id')
            ->selectRaw("\n                t.id AS thread,\n                EXTRACT(YEAR FROM (t.create_date AT TIME ZONE 'UTC' AT TIME ZONE 'America/Lima')) AS anio,\n                EXTRACT(MONTH FROM (t.create_date AT TIME ZONE 'UTC' AT TIME ZONE 'America/Lima')) AS mes,\n                (t.create_date AT TIME ZONE 'UTC' AT TIME ZONE 'America/Lima') AS fecha_thread,\n                m.id AS cod_interaccion,\n                (m.create_date AT TIME ZONE 'UTC' AT TIME ZONE 'America/Lima') AS fecha_interaccion,\n                c.company_name || ' - ' || d.channel_name AS canal,\n                CASE\n                    WHEN COALESCE(m.external_id, '') <> '' THEN ''\n                    WHEN t.assigned_agent_id = 1 OR m.user_id = 1 THEN 'BOT SYSTEM'\n                    WHEN t.assigned_agent_id = 2 OR m.user_id = 2 THEN 'HOLDING'\n                    ELSE u.username\n                END AS asesor,\n                m.origin AS intencion,\n                m.item_type AS tipo_interaccion,\n                m.item_content AS texto_interaccion,\n                t.thread_status AS grupo,\n                cc1.name AS persona,\n                cc2.name AS nombre_original,\n                cc1.phone AS numero_cliente,\n                cc1.email AS correo,\n                cc2.sender_id || ' - ' || cc2.channel_type AS canal_comunicacion\n            ")
            ->whereRaw("(t.create_date AT TIME ZONE 'UTC' AT TIME ZONE 'America/Lima') BETWEEN ? AND ?", [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ])
            ->where('t.company_id', (int) $validated['company_id'])
            ->where('t.communication_channel_id', (int) $validated['communication_channel_id']);
    }

    public function getInteractionsData(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'company_id' => 'required|integer',
            'communication_channel_id' => 'required|integer',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:500',
        ]);

        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = (int) ($validated['per_page'] ?? 100);

        $baseQuery = $this->interactionsBaseQuery($validated);

        $total = (clone $baseQuery)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = (clone $baseQuery)
            ->orderByDesc('t.create_date')
            ->orderByDesc('m.create_date')
            ->orderByDesc('m.id')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
            ],
        ]);
    }

    public function exportInteractions(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'company_id' => 'required|integer',
            'communication_channel_id' => 'required|integer',
        ]);

        $query = $this->interactionsBaseQuery($validated)
            ->orderBy('m.id');

        $totalRows = (clone $query)->count();

        $headers = [
            'Thread',
            'Año',
            'Mes',
            'Fecha Thread',
            'Cod Interacción',
            'Fecha Interacción',
            'Canal',
            'Asesor',
            'Intención',
            'Tipo Interacción',
            'Texto Interacción',
            'Grupo',
            'Persona',
            'Nombre Original',
            'Número Cliente',
            'Correo',
            'Canal Comunicación',
        ];

        $fileName = 'interacciones_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $tmpDir = storage_path('app/tmp/spout');

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $baseTmpPath = tempnam($tmpDir, 'interactions_');
        $filePath = ($baseTmpPath ?: ($tmpDir . DIRECTORY_SEPARATOR . uniqid('interactions_', true))) . '.xlsx';

        if ($baseTmpPath && file_exists($baseTmpPath)) {
            @unlink($baseTmpPath);
        }

        $writer = new XlsxWriter(new XlsxOptions(tempFolder: $tmpDir));
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues($headers));

        $writtenRows = 0;

        foreach ($query->lazy(1000) as $row) {
            $writer->addRow(Row::fromValues([
                $row->thread,
                $row->anio,
                $row->mes,
                $row->fecha_thread,
                $row->cod_interaccion,
                $row->fecha_interaccion,
                $row->canal,
                $row->asesor,
                $row->intencion,
                $row->tipo_interaccion,
                $row->texto_interaccion,
                $row->grupo,
                $row->persona,
                $row->nombre_original,
                $row->numero_cliente,
                $row->correo,
                $row->canal_comunicacion,
            ]));

            $writtenRows++;
        }

        $writer->close();

        Log::info('REPORT_INTERACTIONS_EXPORTED', [
            'user_id' => (int) optional($request->user())->id,
            'user_email' => optional($request->user())->email,
            'downloaded_at' => now()->toDateTimeString(),
            'rows_exported' => $writtenRows,
            'rows_counted' => $totalRows,
            'company_id' => (int) $validated['company_id'],
            'communication_channel_id' => (int) $validated['communication_channel_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'file_name' => $fileName,
        ]);

        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }

    private function threadsBaseQuery(array $validated)
    {
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $threadsFiltered = DB::table('threads as t')
            ->leftJoin('users_laravel as u', 't.assigned_agent_id', '=', 'u.id')
            ->leftJoin('companies as c', 'c.id', '=', 't.company_id')
            ->leftJoin('communication_channels as d', 'd.id', '=', 't.communication_channel_id')
            ->leftJoin('customers as cc1', 'cc1.id', '=', 't.customer_id')
            ->leftJoin('customer_channels as cc2', 'cc2.id', '=', 't.customer_channel_id')
            ->selectRaw("\n                t.id,\n                c.company_name || ' - ' || d.channel_name AS canal,\n                t.thread_status,\n                t.assigned_agent_id,\n                u.username,\n                (t.create_date AT TIME ZONE 'UTC' AT TIME ZONE 'America/Lima') AS create_date,\n                COALESCE(cc1.name, cc2.name) AS cliente,\n                cc1.phone AS numero_cliente,\n                cc2.sender_id || ' - ' || cc2.channel_type AS canal_cliente\n            ")
            ->whereRaw("(t.create_date AT TIME ZONE 'UTC' AT TIME ZONE 'America/Lima') BETWEEN ? AND ?", [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ])
            ->where('t.company_id', (int) $validated['company_id'])
            ->where('t.communication_channel_id', (int) $validated['communication_channel_id']);

        $messagesAgg = DB::table('messages as m')
            ->joinSub($threadsFiltered, 'tf', function ($join) {
                $join->on('tf.id', '=', 'm.thread_id');
            })
            ->selectRaw("\n                m.thread_id,\n                COUNT(*) AS cantidad,\n                COUNT(*) FILTER (WHERE m.item_type = 'text') AS text,\n                COUNT(*) FILTER (WHERE m.item_type = 'image') AS image,\n                COUNT(*) FILTER (WHERE m.item_type = 'file') AS document,\n                COUNT(*) FILTER (\n                    WHERE m.item_type NOT IN ('text', 'image', 'file')\n                        OR m.item_type IS NULL\n                ) AS otro,\n                COUNT(*) FILTER (WHERE COALESCE(m.external_id, '') = '') AS bot,\n                COUNT(*) FILTER (WHERE COALESCE(m.external_id, '') <> '') AS usuario\n            ")
            ->groupBy('m.thread_id');

        return DB::query()
            ->fromSub($threadsFiltered, 'tf')
            ->leftJoinSub($messagesAgg, 'ma', function ($join) {
                $join->on('ma.thread_id', '=', 'tf.id');
            })
            ->selectRaw("\n                tf.id,\n                tf.thread_status,\n                TO_CHAR(tf.create_date, 'YYYY-MM-DD HH24:MI:SS') AS create_date,\n                tf.canal,\n                tf.cliente,\n                tf.canal_cliente,\n                CASE\n                    WHEN tf.assigned_agent_id = 1 THEN 'BOT SYSTEM'\n                    WHEN tf.assigned_agent_id = 2 THEN 'HOLDING'\n                    ELSE tf.username\n                END AS asesor,\n                COALESCE(ma.cantidad, 0) AS cantidad,\n                COALESCE(ma.text, 0) AS text,\n                COALESCE(ma.image, 0) AS image,\n                COALESCE(ma.document, 0) AS document,\n                COALESCE(ma.otro, 0) AS otro,\n                COALESCE(ma.bot, 0) AS bot,\n                COALESCE(ma.usuario, 0) AS usuario\n            ");
    }

    public function getThreadsData(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'company_id' => 'required|integer',
            'communication_channel_id' => 'required|integer',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:500',
        ]);

        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = (int) ($validated['per_page'] ?? 100);

        $baseQuery = $this->threadsBaseQuery($validated);

        $total = (clone $baseQuery)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = (clone $baseQuery)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
            ],
        ]);
    }

    public function exportThreads(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'company_id' => 'required|integer',
            'communication_channel_id' => 'required|integer',
        ]);

        $query = $this->threadsBaseQuery($validated)
            ->orderBy('id');

        $totalRows = (clone $query)->count();

        $headers = [
            'Thread',
            'Estado',
            'Fecha Creación',
            'Canal',
            'Cliente',
            'Canal Cliente',
            'Asesor',
            'Cantidad',
            'Text',
            'Image',
            'Document',
            'Otro',
            'Bot',
            'Usuario',
        ];

        $fileName = 'threads_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        $tmpDir = storage_path('app/tmp/spout');

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $baseTmpPath = tempnam($tmpDir, 'threads_');
        $filePath = ($baseTmpPath ?: ($tmpDir . DIRECTORY_SEPARATOR . uniqid('threads_', true))) . '.xlsx';

        if ($baseTmpPath && file_exists($baseTmpPath)) {
            @unlink($baseTmpPath);
        }

        $writer = new XlsxWriter(new XlsxOptions(tempFolder: $tmpDir));
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues($headers));

        $writtenRows = 0;

        foreach ($query->lazy(1000) as $row) {
            $writer->addRow(Row::fromValues([
                $row->id,
                $row->thread_status,
                $row->create_date,
                $row->canal,
                $row->cliente,
                $row->canal_cliente,
                $row->asesor,
                $row->cantidad,
                $row->text,
                $row->image,
                $row->document,
                $row->otro,
                $row->bot,
                $row->usuario,
            ]));

            $writtenRows++;
        }

        $writer->close();

        Log::info('REPORT_THREADS_EXPORTED', [
            'user_id' => (int) optional($request->user())->id,
            'user_email' => optional($request->user())->email,
            'downloaded_at' => now()->toDateTimeString(),
            'rows_exported' => $writtenRows,
            'rows_counted' => $totalRows,
            'company_id' => (int) $validated['company_id'],
            'communication_channel_id' => (int) $validated['communication_channel_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'file_name' => $fileName,
        ]);

        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }
}
