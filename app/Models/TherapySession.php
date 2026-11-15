<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TherapySession extends Model
{
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

    public function deleteAudio(): void
    {
        if ($this->audio_path && Storage::disk('local')->exists($this->audio_path)) {
            Storage::disk('local')->delete($this->audio_path);
        }

        $this->forceFill([
            'audio_path' => null,
            'audio_mime' => null,
        ])->save();
    }
}
