<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiInboxWhitelistEntry extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $table = 'whitelist';

    protected $fillable = [
        'name',
        'email',
        'notes',
        'is_active',
        'last_matched_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_matched_at' => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
