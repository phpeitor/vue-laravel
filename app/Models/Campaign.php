<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'description',
        'company_id',
        'communication_channel_id',
        'template_id',
        'start_date',
        'end_date',
        'status',
        'type',
        'start_time',
    ];

    public function uploads()
    {
        return $this->hasMany(CampaignUpload::class);
    }

    public function recipients()
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function logs()
    {
        return $this->hasMany(CampaignLog::class);
    }

    public function getStartAtAttribute(): ?Carbon
    {
        if (!$this->start_date) return null;

        if ($this->type === 'Programada' && $this->start_time) {
            // start_date = 'YYYY-MM-DD'
            // start_time = 'HH:MM:SS' (Postgres)
            return Carbon::parse($this->start_date)
                ->setTimeFromTimeString($this->start_time); // respeta HH:MM:SS
        }

        return now();
    }

    public static function finalizeIfDone(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId) {

            // Bloquea la campaña para evitar race conditions (varios jobs terminando a la vez)
            $campaign = self::where('id', $campaignId)->lockForUpdate()->first();

            if (! $campaign) return;

            // Si ya está finalizada, no vuelvas a tocarla
            if (in_array($campaign->status, ['FINALLY', 'FINALLY_FAILED'])) {
                return;
            }

            // ¿Quedan pendientes o enviándose?
            $hasOpen = \App\Models\CampaignRecipient::where('campaign_id', $campaignId)
                ->whereIn('status', ['PENDING', 'SENDING'])
                ->exists();

            if ($hasOpen) {
                return;
            }

            // Ya no hay PENDING/SENDING → decidir estado final
            $hasFailed = \App\Models\CampaignRecipient::where('campaign_id', $campaignId)
                ->where('status', 'FAILED')
                ->exists();

            $campaign->update([
                'status' => $hasFailed ? 'FINALLY_FAILED' : 'FINALLY',
            ]);
        });
    }

}
