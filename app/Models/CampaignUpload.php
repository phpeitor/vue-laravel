<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignUpload extends Model
{
    protected $fillable = [
        'campaign_id',
        'file_name',
        'file_path',
        'total_rows',
        'valid_rows',
        'invalid_rows',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function recipients()
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
