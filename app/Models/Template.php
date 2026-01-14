<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $table = 'message_templates';

    protected $casts = [
        'components' => 'array',
    ];

    public $timestamps = false;
}
