<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'alternative',
        'position',
        'operation_code',
        'name',
        'note',
        'unit',
        'unit_value',
        'normative',
        'va',
        'primary_class',
        'secondary_class',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
