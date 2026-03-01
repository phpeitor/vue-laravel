<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

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

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_communication_channels',
            'communication_channel_id',
            'user_id'
        )->withPivot(['company_id'])->withTimestamps();
    }
}
