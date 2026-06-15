<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorBeach extends Model
{
    protected $table = 'operator_beach';

    public $timestamps = false;

    protected $fillable = [
        'operator_id',
        'beach_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'operator_id' => 'integer',
            'beach_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }
}
