<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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

    public function getInteractionsData(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'company_id' => 'required|integer',
            'communication_channel_id' => 'required|integer',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $query = DB::select(
            'SELECT 
                t.id AS thread,
                EXTRACT(YEAR FROM (t.create_date AT TIME ZONE \'UTC\' AT TIME ZONE \'America/Lima\')) AS anio,
                EXTRACT(MONTH FROM (t.create_date AT TIME ZONE \'UTC\' AT TIME ZONE \'America/Lima\')) AS mes,
                (t.create_date AT TIME ZONE \'UTC\' AT TIME ZONE \'America/Lima\') AS fecha_thread,
                m.id AS cod_interaccion,
                (m.create_date AT TIME ZONE \'UTC\' AT TIME ZONE \'America/Lima\') AS fecha_interaccion,
                c.company_name || \' - \' || d.channel_name AS canal,
                CASE
                    WHEN COALESCE(external_id, \'\') <> \'\' THEN \'\'
                    WHEN assigned_agent_id = 1  or user_id=1 THEN \'BOT SYSTEM\'
                    WHEN assigned_agent_id = 2 or user_id=2 THEN \'HOLDING\'
                    ELSE u.username
                END AS asesor,
                m.origin AS intencion,
                item_type AS tipo_interaccion,
                item_content AS texto_interaccion,
                t.thread_status as grupo,
                cc1.name AS persona,
                cc2.name AS nombre_original,
                cc1.phone AS numero_cliente,
                cc1.email AS correo,
                cc2.sender_id || \' - \' || cc2.channel_type AS canal_comunicacion
            FROM public.threads t
            LEFT JOIN public.messages m ON t.id = m.thread_id
            LEFT JOIN public.users_laravel u ON t.assigned_agent_id = u.id
            LEFT JOIN public.companies c ON c.id = t.company_id
            LEFT JOIN public.communication_channels d ON d.id = t.communication_channel_id
            LEFT JOIN public.customers cc1 ON cc1.id = t.customer_id
            LEFT JOIN public.customer_channels cc2 ON cc2.id = t.customer_channel_id
            WHERE (t.create_date AT TIME ZONE \'UTC\' AT TIME ZONE \'America/Lima\')
            BETWEEN ? AND ?
            AND t.company_id = ?
            AND t.communication_channel_id = ?
            ORDER BY fecha_thread DESC, fecha_interaccion DESC',
            [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
                $validated['company_id'],
                $validated['communication_channel_id'],
            ]
        );

        return response()->json([
            'data' => $query,
            'count' => count($query),
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

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $query = DB::select(
            'SELECT 
                t.id AS thread,
                EXTRACT(YEAR FROM (t.create_date AT TIME ZONE \'UTC\' AT TIME ZONE \'America/Lima\')) AS anio,
                EXTRACT(MONTH FROM (t.create_date AT TIME ZONE \'UTC\' AT TIME ZONE \'America/Lima\')) AS mes,
                (t.create_date AT TIME ZONE \'UTC\' AT TIME ZONE \'America/Lima\') AS fecha_thread,
                m.id AS cod_interaccion,
                (m.create_date AT TIME ZONE \'UTC\' AT TIME ZONE \'America/Lima\') AS fecha_interaccion,
                c.company_name || \' - \' || d.channel_name AS canal,
                CASE
                    WHEN COALESCE(external_id, \'\') <> \'\' THEN \'\'
                    WHEN assigned_agent_id = 1  or user_id=1 THEN \'BOT SYSTEM\'
                    WHEN assigned_agent_id = 2 or user_id=2 THEN \'HOLDING\'
                    ELSE u.username
                END AS asesor,
                m.origin AS intencion,
                item_type AS tipo_interaccion,
                item_content AS texto_interaccion,
                t.thread_status as grupo,
                cc1.name AS persona,
                cc2.name AS nombre_original,
                cc1.phone AS numero_cliente,
                cc1.email AS correo,
                cc2.sender_id || \' - \' || cc2.channel_type AS canal_comunicacion
            FROM public.threads t
            LEFT JOIN public.messages m ON t.id = m.thread_id
            LEFT JOIN public.users_laravel u ON t.assigned_agent_id = u.id
            LEFT JOIN public.companies c ON c.id = t.company_id
            LEFT JOIN public.communication_channels d ON d.id = t.communication_channel_id
            LEFT JOIN public.customers cc1 ON cc1.id = t.customer_id
            LEFT JOIN public.customer_channels cc2 ON cc2.id = t.customer_channel_id
            WHERE (t.create_date AT TIME ZONE \'UTC\' AT TIME ZONE \'America/Lima\')
            BETWEEN ? AND ?
            AND t.company_id = ?
            AND t.communication_channel_id = ?
            ORDER BY fecha_thread DESC, fecha_interaccion DESC',
            [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
                $validated['company_id'],
                $validated['communication_channel_id'],
            ]
        );

        return response()->json([
            'data' => $query,
            'filename' => 'interacciones_' . now()->format('Y-m-d_H-i-s') . '.csv'
        ]);
    }
}
