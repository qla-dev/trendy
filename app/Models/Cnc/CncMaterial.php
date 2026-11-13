<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncMaterial extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_materials';

    protected $fillable = [
        'oznaka', 'naziv', 'grupa', 'standard',
        'gustina', 'cijena_po_kg', 'aktivno', 'napomena',
    ];

    protected $casts = [
        'gustina' => 'float',
        'cijena_po_kg' => 'float',
        'aktivno' => 'boolean',
    ];
}
