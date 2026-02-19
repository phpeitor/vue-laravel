<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $table = 'customers';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'company_id',
        'create_date',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'customer_id', 'id');
    }
}
