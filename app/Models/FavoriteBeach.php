<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoriteBeach extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'visitor_id',
        'beach_id',
        'created_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'visitor_id' => 'integer',
        'beach_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }
}
