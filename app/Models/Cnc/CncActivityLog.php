<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncActivityLog extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_activity_logs';

    protected $guarded = ['id'];
}
