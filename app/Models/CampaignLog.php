<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignLog extends Model
{
    protected $fillable = [
        'campaign_id',
        'type',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}

