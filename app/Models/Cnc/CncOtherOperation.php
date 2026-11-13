<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncOtherOperation extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_other_operations';

    protected $fillable = [
        'naziv', 'cijena', 'tip_cijene', 'aktivno', 'napomena',
    ];

    protected $casts = [
        'cijena' => 'float',
        'aktivno' => 'boolean',
    ];
}
