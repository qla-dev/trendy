<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncQuoteContactHistory extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_quote_contact_history';

    protected $guarded = ['id'];
}
