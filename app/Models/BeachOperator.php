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
        'login',
        'password',
        'operator_hash',
        'name',
        'last_name',
        'first_name',
        'middle_name',
        'work_phone',
    ];

    protected $hidden = [
        'password',
        'operator_hash',
    ];

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }
}
