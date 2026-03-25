<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CommunicationChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataController extends Controller
{
    public function companies(Request $request)
    {
        $userId = $request->user()->id;

        $assignments = DB::table('user_communication_channels')
            ->where('user_id', $userId)
            ->get(['company_id']);

        $hasAssignments = $assignments->isNotEmpty();

        $companiesQuery = Company::select('id', 'company_name')->orderBy('company_name');

        if ($hasAssignments) {
            $companyIds = $assignments->pluck('company_id')->unique()->values();
            $companiesQuery->whereIn('id', $companyIds);
        }

        $companies = $companiesQuery->get();

        return response()->json($companies);
    }

    public function communicationChannels(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer'],
        ]);

        $userId = $request->user()->id;

        $assignments = DB::table('user_communication_channels')
            ->where('user_id', $userId)
            ->get(['company_id', 'communication_channel_id']);

        $hasAssignments = $assignments->isNotEmpty();
        $companyId = (int) ($validated['company_id'] ?? 0);

        $channelsQuery = CommunicationChannel::select('id', 'channel_name', 'company_id')
            ->where('status', 'ACTIVO')
            ->orderBy('channel_name')
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId));

        if ($hasAssignments) {
            $allowedChannelIds = $assignments
                ->when($companyId > 0, fn ($rows) => $rows->where('company_id', $companyId))
                ->pluck('communication_channel_id')
                ->unique()
                ->values();

            if ($allowedChannelIds->isEmpty()) {
                return response()->json([]);
            }

            $channelsQuery->whereIn('id', $allowedChannelIds);
        }

        $channels = $channelsQuery->get();

        return response()->json($channels);
    }
}
