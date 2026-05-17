<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeachOperator extends Model
{
    use HasFactory;

    protected $fillable = [
        'beach_id',
        'operator_hash',
        'name',
    ];

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }
}
