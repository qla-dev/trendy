<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncQuote extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_quotes';

    protected $guarded = ['id'];

    protected $casts = [
        'transport_included' => 'boolean',
        'staffelpreise_aktivno' => 'boolean',
        'staffel_kolicine' => 'array',
        'datum' => 'date',
        'total_cost' => 'float',
        'total_selling_price' => 'float',
        'margin_percent' => 'float',
    ];

    public function positions()
    {
        return $this->hasMany(CncQuotePosition::class, 'quote_id');
    }

    public function contactHistory()
    {
        return $this->hasMany(CncQuoteContactHistory::class, 'quote_id');
    }

    public function customer()
    {
        return $this->belongsTo(CncCustomer::class, 'kupac_id');
    }
}
