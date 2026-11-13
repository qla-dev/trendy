<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncPosition extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_positions';

    protected $guarded = ['id'];
}
