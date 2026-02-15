<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'messages';

    protected $primaryKey = 'id';

    public $timestamps = false; // usas create_date

    protected $fillable = [
        'thread_id',
        'customer_id',
        'external_id',
        'item_type',
        'item_content',
        'origin',
        'create_date',
        'last_outgoing_message_id',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }
}
