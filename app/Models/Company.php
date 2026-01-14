<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\CommunicationChannel;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $fillable = [
        'company_name',
    ];

    // 🔗 Relación con canales
    public function communicationChannels()
    {
        return $this->hasMany(CommunicationChannel::class);
    }
}
