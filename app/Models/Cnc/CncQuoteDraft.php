<?php

namespace App\Models\Cnc;

use Illuminate\Database\Eloquent\Model;

class CncQuoteDraft extends Model
{
    protected $connection = 'mysql';
    protected $table = 'cnc_quote_drafts';

    protected $guarded = ['id'];

    protected $casts = [
        'state' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(CncUser::class, 'owner_id');
    }
}
