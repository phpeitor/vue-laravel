<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class IssueController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['required', Rule::in(['BUG_REPORT', 'SUGGESTION'])],
            'title' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string'],
            'severity' => ['nullable', Rule::in(['MINOR', 'MODERATE', 'CRITICAL'])],
        ]);

        if ($validated['tipo'] === 'BUG_REPORT' && empty($validated['severity'])) {
            return response()->json([
                'success' => false,
                'message' => 'La severidad es obligatoria para bug report.',
            ], 422);
        }

        DB::table('issues')->insert([
            'id_user' => $request->user()->id,
            'tipo' => $validated['tipo'],
            'title' => $validated['title'],
            'descripcion' => $validated['descripcion'],
            'severity' => $validated['tipo'] === 'SUGGESTION' ? null : $validated['severity'],
            'create_date' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Incidencia registrada correctamente.',
        ]);
    }
}
