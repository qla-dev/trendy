<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncPersonalAccessToken extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_personal_access_tokens';

    protected $fillable = [
        'cnc_user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public string $plainTextToken = '';

    public function user()
    {
        return $this->belongsTo(CncUser::class, 'cnc_user_id');
    }
}
