<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeachOperatorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'beach_operator_id',
        'beach_id',
        'submitted_at',
        'expires_at',
        'operator_status',
        'operator_warning',
        'operator_wave_direction',
        'operator_wave_azimuth',
        'operator_wave_period',
        'operator_access_status',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'expires_at' => 'datetime',
        'operator_wave_azimuth' => 'integer',
        'operator_wave_period' => 'integer',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(BeachOperator::class, 'beach_operator_id');
    }

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }
}
