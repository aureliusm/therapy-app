<?php

namespace App\Models;

use Database\Factories\TherapySessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TherapySession extends Model
{
    /** @use HasFactory<TherapySessionFactory> */
    use HasFactory;

    protected $table = 'therapy_sessions';

    protected $fillable = [
        'client_id',
        'session_date',
        'therapy_type',
        'audio_path',
        'audio_mime',
        'transcript',
        'summary',
        'topics',
        'interventions',
        'warnings',
        'next_ideas',
        'other_approaches',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'topics' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (TherapySession $session): void {
            if ($session->audio_path) {
                Storage::disk('local')->delete($session->audio_path);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function hasAudio(): bool
    {
        return filled($this->audio_path) && Storage::disk('local')->exists($this->audio_path);
    }

    /**
     * Pretvori besedilo z alinejami (-, •, 1.) v seznam za prikaz.
     *
     * @return list<string>
     */
    public static function bulletItems(?string $text): array
    {
        if (! filled($text)) {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = preg_split('/\n+/', $normalized) ?: [];
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line === '—' || $line === '-') {
                continue;
            }

            $line = preg_replace('/^([-*•–—]|\d+[.)])\s+/u', '', $line) ?? $line;
            $line = trim($line);

            if ($line !== '') {
                $items[] = $line;
            }
        }

        return $items;
    }

    public function deleteAudio(): void
    {
        if ($this->audio_path) {
            Storage::disk('local')->delete($this->audio_path);
        }

        $this->forceFill([
            'audio_path' => null,
            'audio_mime' => null,
        ])->save();
    }
}
