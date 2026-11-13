<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncCustomer extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_customers';

    protected $fillable = [
        'naziv', 'adresa', 'postanski_broj', 'grad', 'drzava',
        'kontakt_osoba', 'email', 'telefon', 'aktivan', 'napomena',
    ];

    protected $casts = [
        'aktivan' => 'boolean',
    ];
}
