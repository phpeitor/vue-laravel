<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\CommunicationChannel;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $table = 'users_laravel';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'estado',
        'omnichannel_user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function scopeSearch(Builder $query, Request $request)
    {
        $term = trim((string) $request->search);

        if ($term === '') {
            return $query;
        }

        $normalized = mb_strtolower($term, 'UTF-8');

        if ($normalized === 'online') {
            $onlineThreshold = now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();

            return $query->whereExists(function ($sub) use ($onlineThreshold) {
                $sub->selectRaw('1')
                    ->from('sessions as s')
                    ->whereColumn('s.user_id', 'users_laravel.id')
                    ->where('s.last_activity', '>=', $onlineThreshold);
            });
        }

        return $query->where(function ($w) use ($term) {
            $w->where('name', 'like', '%' . $term . '%')
                ->orWhere('username', 'like', '%' . $term . '%')
                ->orWhere('email', 'like', '%' . $term . '%');
        });
    }

    public function communicationChannels()
    {
        return $this->belongsToMany(
            CommunicationChannel::class,
            'user_communication_channels',     // tabla pivot
            'user_id',                         // FK en pivot hacia users_laravel
            'communication_channel_id'          // FK en pivot hacia communication_channels
        )->withPivot(['company_id'])->withTimestamps();
    }
}
