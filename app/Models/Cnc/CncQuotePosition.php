<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncQuotePosition extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_quote_positions';

    protected $guarded = ['id'];

    protected $casts = [
        'surface_protection' => 'array',
        'heat_treatment' => 'array',
        'other_operation' => 'array',
        'staffelpreise' => 'array',
        'staffel_kolicine' => 'array',
    ];

    public function quote()
    {
        return $this->belongsTo(CncQuote::class, 'quote_id');
    }
}
