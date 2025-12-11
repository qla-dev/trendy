<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Composition extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'alternative',
        'position',
        'article_code',
        'description',
        'image_url',
        'note',
        'quantity',
        'unit',
        'series',
        'normative',
        'active',
        'final',
        'va',
        'primary_class',
        'secondary_class',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
