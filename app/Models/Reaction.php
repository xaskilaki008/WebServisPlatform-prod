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
        'visitor_id',
        'reaction_type',
    ];

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }
}
