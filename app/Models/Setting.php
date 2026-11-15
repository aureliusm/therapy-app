<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'default_therapy_type',
        'base_prompt',
        'gemini_api_key',
    ];

    protected $hidden = [
        'gemini_api_key',
    ];

    protected function casts(): array
    {
        return [
            'gemini_api_key' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'default_therapy_type' => 'Transakcijska analiza (TA)',
            'base_prompt' => "Si izkušena psihoterapevtka. Piši v slovenščini, toplo, jasno in strokovno.\n"
                ."Na podlagi zapiska srečanja pripravi:\n"
                ."- povzetek (približno pol strani A4),\n"
                ."- ključne teme,\n"
                ."- uporabljene intervence glede na vrsto terapije,\n"
                ."- opažanja in opozorila (na kaj biti pozorna),\n"
                ."- ideje in intervence za naslednje srečanje.\n"
                .'Ne izmišljuj dejstev, ki niso v zapisku.',
        ]);
    }

    /**
     * Ključ iz nastavitev ima prednost; .env je samo za razvoj, ker se iz builda odstrani.
     */
    public static function geminiApiKey(): ?string
    {
        try {
            $storedKey = static::current()->gemini_api_key;
        } catch (DecryptException) {
            $storedKey = null;
        }

        return $storedKey ?: config('therapy.gemini.api_key');
    }
}
