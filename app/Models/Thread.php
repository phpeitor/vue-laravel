<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    protected $table = 'threads';

    // Si tu PK es id autoincrement ok
    protected $primaryKey = 'id';

    public $timestamps = false; // porque en tu tabla usas create_date, etc.

    protected $fillable = [
        'company_id',
        'communication_channel_id',
        'assigned_agent_id',
        'thread_status',
        'first_conversation_date',
        'last_conversation_date',
        'create_date',
        'sender_id',
        'origin',
        'last_outgoing_message_id',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id', 'id');
    }
}
