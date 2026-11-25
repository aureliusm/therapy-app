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
        $settings = static::query()->firstOrCreate([], [
            'default_therapy_type' => 'Transakcijska analiza (TA)',
            'base_prompt' => static::defaultBasePrompt(),
        ]);

        if (in_array($settings->base_prompt, [static::legacyBasePrompt(), static::previousDefaultBasePrompt()], true)) {
            $settings->update(['base_prompt' => static::defaultBasePrompt()]);
        }

        return $settings;
    }

    public static function defaultBasePrompt(): string
    {
        return "Si izkušena psihoterapevtka. Piši v slovenščini, toplo, jasno in strokovno.\n"
            ."Na podlagi zapiska srečanja pripravi:\n"
            ."- povzetek (približno pol strani A4),\n"
            ."- uporabljene intervence glede na vrsto terapije,\n"
            ."- opažanja in opozorila (na kaj biti pozorna),\n"
            ."- ideje in intervence za naslednje srečanje.\n"
            .'Ne izmišljuj dejstev, ki niso v zapisku. Za vsako intervenco, opažanje, idejo in priporočilo druge smeri pripravi kratko ime, daljšo razlago v več stavkih in konkreten primer: dobesedni stavek ali natančen potek vaje.';
    }

    /**
     * Poziv brez tem, ki je razlago in primer še prosil prekratko.
     */
    public static function previousDefaultBasePrompt(): string
    {
        return "Si izkušena psihoterapevtka. Piši v slovenščini, toplo, jasno in strokovno.\n"
            ."Na podlagi zapiska srečanja pripravi:\n"
            ."- povzetek (približno pol strani A4),\n"
            ."- uporabljene intervence glede na vrsto terapije,\n"
            ."- opažanja in opozorila (na kaj biti pozorna),\n"
            ."- ideje in intervence za naslednje srečanje.\n"
            .'Ne izmišljuj dejstev, ki niso v zapisku. Za vsako intervenco, opažanje in idejo pripravi kratko ime, daljšo razlago in konkreten primer uporabe.';
    }

    /**
     * Prejšnji privzeti poziv, ki je še prosil za ključne teme.
     */
    public static function legacyBasePrompt(): string
    {
        return "Si izkušena psihoterapevtka. Piši v slovenščini, toplo, jasno in strokovno.\n"
            ."Na podlagi zapiska srečanja pripravi:\n"
            ."- povzetek (približno pol strani A4),\n"
            ."- ključne teme,\n"
            ."- uporabljene intervence glede na vrsto terapije,\n"
            ."- opažanja in opozorila (na kaj biti pozorna),\n"
            ."- ideje in intervence za naslednje srečanje.\n"
            .'Ne izmišljuj dejstev, ki niso v zapisku.';
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
