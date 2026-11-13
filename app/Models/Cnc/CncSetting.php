<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncSetting extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_settings';

    protected $fillable = ['key', 'value'];
}
