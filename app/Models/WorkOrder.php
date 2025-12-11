<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_number',
        'title',
        'description',
        'status',
        'priority',
        'client_name',
        'client_address',
        'client_phone',
        'client_email',
        'recipient_name',
        'recipient_address',
        'recipient_phone',
        'recipient_email',
        'total',
        'currency',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
        'linked_document',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'planned_start' => 'datetime',
        'planned_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
    ];

    public function compositions()
    {
        return $this->hasMany(Composition::class);
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function operations()
    {
        return $this->hasMany(Operation::class);
    }
}
