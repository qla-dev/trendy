<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncSurfaceProtection extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_surface_protections';

    protected $fillable = [
        'naziv', 'cijena', 'tip_cijene', 'aktivno', 'napomena',
    ];

    protected $casts = [
        'cijena' => 'float',
        'aktivno' => 'boolean',
    ];
}
