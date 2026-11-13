<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class CncUser extends Authenticatable
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'cnc_users';

    protected $fillable = [
        'ime_prezime',
        'email',
        'password',
        'role',
        'aktivan',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'aktivan' => 'boolean',
        'permissions' => 'array',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function tokens()
    {
        return $this->hasMany(CncPersonalAccessToken::class, 'cnc_user_id');
    }

    public function createToken(string $name): CncPersonalAccessToken
    {
        $plainToken = bin2hex(random_bytes(32));

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainToken),
            'abilities' => json_encode(['*']),
        ]);

        $token->plainTextToken = $plainToken;

        return $token;
    }
}
