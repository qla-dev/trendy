<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncMachine extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_machines';

    protected $fillable = [
        'oznaka', 'naziv', 'tip',
        'satnica_programiranja', 'satnica_pripreme', 'satnica_obrade',
        'aktivna', 'napomena',
    ];

    protected $casts = [
        'satnica_programiranja' => 'float',
        'satnica_pripreme' => 'float',
        'satnica_obrade' => 'float',
        'aktivna' => 'boolean',
    ];
}
