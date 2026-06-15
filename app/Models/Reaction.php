<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'beach_id',
        'user_id',
        'visitor_id',
        'reaction_type',
    ];

    protected $casts = [
        'beach_id' => 'integer',
        'user_id' => 'integer',
        'visitor_id' => 'integer',
    ];

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}
