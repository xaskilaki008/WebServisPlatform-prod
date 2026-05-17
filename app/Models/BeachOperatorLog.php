<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BeachOperatorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'beach_operator_id',
        'beach_id',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];
}
