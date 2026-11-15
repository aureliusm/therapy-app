<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TherapySession;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionAudioController extends Controller
{
    public function __invoke(Client $client, TherapySession $session): StreamedResponse
    {
        abort_unless($session->client_id === $client->id, 404);
        abort_unless($session->hasAudio(), 404);

        return Storage::disk('local')->response(
            $session->audio_path,
            basename($session->audio_path),
            [
                'Content-Type' => $session->audio_mime ?: 'audio/webm',
            ],
        );
    }
}
