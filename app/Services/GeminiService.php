<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeminiService
{
    public function transcribe(string $absolutePath, string $mimeType): string
    {
        $chunks = $this->chunkAudio($absolutePath, $mimeType);
        $parts = [];

        try {
            foreach ($chunks as $index => $chunkPath) {
                $parts[] = $this->generate(
                    config('therapy.gemini.transcribe_model'),
                    [
                        ['text' => 'Prepiši govorjeni zapis v slovenščini. Vrni samo čisto besedilo transkripta, brez uvoda in komentarjev. Če je več govorcev, jih poimenuj Terapevtka in Klient, če je to razvidno.'],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => base64_encode((string) file_get_contents($chunkPath)),
                            ],
                        ],
                    ],
                );

                if ($index < count($chunks) - 1) {
                    Sleep::sleep(2);
                }
            }
        } finally {
            $this->cleanupChunks($chunks, $absolutePath);
        }

        return trim(implode("\n\n", array_filter($parts)));
    }

    /**
     * @param  list<string>  $chunks
     */
    protected function cleanupChunks(array $chunks, string $originalPath): void
    {
        $chunkDirectory = null;

        foreach ($chunks as $chunkPath) {
            if ($chunkPath === $originalPath) {
                continue;
            }

            $chunkDirectory = dirname($chunkPath);
            @unlink($chunkPath);
        }

        if ($chunkDirectory !== null) {
            @rmdir($chunkDirectory);
        }
    }

    /**
     * @return array{
     *     summary: string,
     *     topics: list<string>,
     *     interventions: string,
     *     warnings: string,
     *     next_ideas: string
     * }
     */
    public function summarize(string $transcript, string $therapyType, string $basePrompt, ?string $previousContext = null): array
    {
        $prompt = trim($basePrompt)."\n\n"
            ."Vrsta terapije za to srečanje: {$therapyType}.\n"
            ."Intervence in predloge prilagodi tej vrsti terapije.\n\n"
            ."Vrni JSON s ključi:\n"
            ."- summary: povzetek, približno pol strani A4,\n"
            ."- topics: seznam ključnih tem (kratke fraze),\n"
            ."- interventions: seznam uporabljenih intervenc,\n"
            ."- warnings: seznam opažanj in opozoril,\n"
            ."- next_ideas: seznam idej in intervenc za naslednje srečanje.\n\n"
            .($previousContext ? "Kratek kontekst prejšnjih srečanj:\n{$previousContext}\n\n" : '')
            ."Zapisek / transkript srečanja:\n{$transcript}";

        $stringList = ['type' => 'ARRAY', 'items' => ['type' => 'STRING']];

        $text = $this->generate(
            config('therapy.gemini.summary_model'),
            [['text' => $prompt]],
            [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'summary' => ['type' => 'STRING'],
                        'topics' => $stringList,
                        'interventions' => $stringList,
                        'warnings' => $stringList,
                        'next_ideas' => $stringList,
                    ],
                    'required' => ['summary', 'topics', 'interventions', 'warnings', 'next_ideas'],
                ],
            ],
        );

        $json = $this->extractJson($text);

        return [
            'summary' => $this->stringValue($json['summary'] ?? ''),
            'topics' => $this->listValue($json['topics'] ?? []),
            'interventions' => $this->bulletText($json['interventions'] ?? ''),
            'warnings' => $this->bulletText($json['warnings'] ?? ''),
            'next_ideas' => $this->bulletText($json['next_ideas'] ?? ''),
        ];
    }

    protected function stringValue(mixed $value): string
    {
        if (is_array($value)) {
            return trim(implode("\n", $this->listValue($value)));
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return list<string>
     */
    protected function listValue(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $value,
        )));
    }

    /**
     * Seznam shrani kot alineje ("- ..."), ki jih prikaz in urejanje že razumeta.
     */
    protected function bulletText(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        return implode("\n", array_map(
            fn (string $item): string => '- '.ltrim($item, "-*• \t"),
            $this->listValue($value),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @param  array<string, mixed>  $generationConfig
     */
    protected function generate(string $model, array $parts, array $generationConfig = []): string
    {
        $apiKey = Setting::geminiApiKey();

        if (! filled($apiKey)) {
            throw new RuntimeException('Manjka Gemini API ključ. Vnesi ga v Nastavitvah.');
        }

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
        ];

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        try {
            $response = Http::timeout(90)
                ->acceptJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->retry(
                    3,
                    fn (int $attempt): int => $attempt * 3000,
                    fn (Throwable $e): bool => $e instanceof RequestException
                        && in_array($e->response->status(), [429, 500, 503], true),
                    throw: false,
                )
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                    $payload,
                );
        } catch (ConnectionException) {
            throw new RuntimeException('Gemini ne odgovarja (timeout). Preveri internet ali poskusi znova.');
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message') ?: Str::limit($response->body(), 300);

            throw new RuntimeException("Gemini napaka ({$response->status()}): {$message}");
        }

        $text = collect(data_get($response->json(), 'candidates.0.content.parts', []))
            ->reject(fn (array $part): bool => (bool) ($part['thought'] ?? false))
            ->pluck('text')
            ->filter()
            ->implode('');

        if (! filled($text)) {
            $reason = data_get($response->json(), 'candidates.0.finishReason')
                ?? data_get($response->json(), 'promptFeedback.blockReason');

            throw new RuntimeException('Gemini ni vrnil besedila'.($reason ? " ({$reason})." : '.'));
        }

        return trim($text);
    }

    /**
     * @return list<string>
     */
    protected function chunkAudio(string $absolutePath, string $mimeType): array
    {
        $maxBytes = config('therapy.gemini.max_chunk_bytes');
        $size = filesize($absolutePath) ?: 0;

        if ($size <= $maxBytes) {
            return [$absolutePath];
        }

        $ffmpeg = $this->ffmpegPath();

        if ($ffmpeg === null || ! Str::contains($mimeType, ['webm', 'ogg', 'mp3', 'mpeg', 'wav', 'mp4', 'm4a'])) {
            return [$absolutePath];
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) ?: 'webm';
        $extension = ['weba' => 'webm', 'oga' => 'ogg', 'mpga' => 'mp3'][$extension] ?? $extension;
        $dir = sys_get_temp_dir().'/therapy-audio-'.Str::random(8);
        mkdir($dir);

        $pattern = $dir.'/chunk_%03d.'.$extension;
        $result = Process::timeout(120)->run([
            $ffmpeg,
            '-y',
            '-i', $absolutePath,
            '-f', 'segment',
            '-segment_time', '180',
            '-c', 'copy',
            $pattern,
        ]);

        if (! $result->successful()) {
            @array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir);

            return [$absolutePath];
        }

        $chunks = collect(glob($dir.'/chunk_*.'.$extension) ?: [])
            ->sort()
            ->values()
            ->all();

        return $chunks !== [] ? $chunks : [$absolutePath];
    }

    /**
     * Zapakirana aplikacija, zagnana iz Finderja, nima Homebrew poti v PATH.
     */
    protected function ffmpegPath(): ?string
    {
        foreach (['/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $result = Process::run(['which', 'ffmpeg']);

        return $result->successful() && filled(trim($result->output())) ? trim($result->output()) : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractJson(string $text): array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```json\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/^```\s*/', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;

        $decoded = json_decode($clean, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $clean, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('Gemini ni vrnil veljavnega JSON odgovora.');
    }
}
