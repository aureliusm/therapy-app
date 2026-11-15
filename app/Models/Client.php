<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'therapy_type',
        'notes',
    ];

    protected static function booted(): void
    {
        // Kaskadno brisanje v bazi ne sproži model eventov, zato posnetke pobrišemo tukaj.
        static::deleting(function (Client $client): void {
            $client->sessions()->each(fn (TherapySession $session) => $session->delete());
        });
    }

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
