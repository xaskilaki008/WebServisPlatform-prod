<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_hash',
        'nickname',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function favoriteBeaches(): HasMany
    {
        return $this->hasMany(FavoriteBeach::class);
    }
}
