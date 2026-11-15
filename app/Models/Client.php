<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    protected $fillable = [
        'name',
        'therapy_type',
        'notes',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(TherapySession::class)->orderByDesc('session_date')->orderByDesc('id');
    }

    public function latestSession(): HasOne
    {
        return $this->hasOne(TherapySession::class)->latestOfMany('session_date');
    }

    public function resolvedTherapyType(?string $fallback = null): string
    {
        return $this->therapy_type
            ?: $fallback
            ?: Setting::current()->default_therapy_type;
    }
}
