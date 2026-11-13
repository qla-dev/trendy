<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncHeatTreatment extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_heat_treatments';

    protected $fillable = [
        'naziv', 'cijena', 'tip_cijene', 'aktivno', 'napomena',
    ];

    protected $casts = [
        'cijena' => 'float',
        'aktivno' => 'boolean',
    ];
}
