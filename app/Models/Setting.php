<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'default_therapy_type',
        'base_prompt',
    ];

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
}
