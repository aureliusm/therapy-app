<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\TherapySession;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessTherapySession implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public int $backoff = 15;

    public function __construct(
        public int $sessionId,
        public bool $retranscribe = false,
    ) {}

    public function handle(GeminiService $gemini): void
    {
        $session = TherapySession::query()->with('client')->find($this->sessionId);

        if (! $session) {
            return;
        }

        $session->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            $transcript = trim((string) $session->transcript);

            if ($session->hasAudio() && ($this->retranscribe || $transcript === '')) {
                $absolute = Storage::disk('local')->path($session->audio_path);
                $mime = $session->audio_mime ?: 'audio/webm';
                $transcript = $gemini->transcribe($absolute, $mime);
                $session->update(['transcript' => $transcript]);
            }

            $transcript = trim((string) $session->fresh()?->transcript);

            if ($transcript === '') {
                throw new \RuntimeException('Ni transkripta ali besedila za povzetek.');
            }

            $settings = Setting::current();
            $previous = TherapySession::query()
                ->where('client_id', $session->client_id)
                ->where('id', '<', $session->id)
                ->where('status', 'ready')
                ->orderByDesc('session_date')
                ->orderByDesc('id')
                ->limit(3)
                ->get()
                ->map(function (TherapySession $item) {
                    $date = $item->session_date?->format('d.m.Y') ?? '';

                    return trim($date.': '.Str::limit((string) $item->summary, 280));
                })
                ->filter()
                ->implode("\n");

            $result = $gemini->summarize(
                $transcript,
                $session->therapy_type,
                (string) $settings->base_prompt,
                $previous !== '' ? $previous : null,
            );

            $session->update([
                'summary' => $result['summary'],
                'topics' => $result['topics'],
                'interventions' => $result['interventions'],
                'warnings' => $result['warnings'],
                'next_ideas' => $result['next_ideas'],
                'other_approaches' => $result['other_approaches'],
                'status' => 'ready',
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            $session->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Pokliče se tudi ob timeoutu, ko handle() ne pride do catch bloka.
     */
    public function failed(?Throwable $exception): void
    {
        TherapySession::query()
            ->whereKey($this->sessionId)
            ->where('status', 'processing')
            ->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage() ?: 'Obdelava ni uspela. Poskusi znova.',
            ]);
    }
}
