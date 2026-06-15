<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Operator extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'work_phone',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function beaches(): BelongsToMany
    {
        return $this->belongsToMany(Beach::class, 'operator_beach')
            ->withPivot('created_at');
    }
}
