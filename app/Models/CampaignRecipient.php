<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'campaign_upload_id',
        'phone',
        'variables',
        'status',
        'provider_message_id',
        'error_message',
    ];

    protected $casts = [
        'variables' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function upload()
    {
        return $this->belongsTo(CampaignUpload::class, 'campaign_upload_id');
    }
}

