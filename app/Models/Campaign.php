<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Campaign.php
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
}
