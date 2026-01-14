<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationChannel extends Model
{
    use HasFactory;

    protected $table = 'communication_channels';

    protected $fillable = [
        'company_id',
        'channel_name',
        'channel_type',
        'status',
    ];

    /**
     * Relación con Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
