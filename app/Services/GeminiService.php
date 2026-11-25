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
     *     interventions: string,
     *     warnings: string,
     *     next_ideas: string,
     *     insight_details: array{
     *         interventions: list<array{title: string, explanation: string, example: string}>,
     *         warnings: list<array{title: string, explanation: string, example: string}>,
     *         next_ideas: list<array{title: string, explanation: string, example: string}>,
     *         other_approaches: list<array{title: string, explanation: string, example: string}>
     *     },
     *     other_approaches: string
     * }
     */
    public function summarize(string $transcript, string $therapyType, string $basePrompt, ?string $previousContext = null): array
    {
        $otherApproaches = implode(', ', $this->otherApproaches($therapyType));

        $prompt = trim($basePrompt)."\n\n"
            ."Vrsta terapije za to srečanje: {$therapyType}.\n"
            ."Intervence in predloge prilagodi tej vrsti terapije.\n\n"
            ."Vrni JSON s ključi:\n"
            ."- summary: povzetek, približno pol strani A4,\n"
            ."- interventions: seznam uporabljenih intervenc. Za vsako vrni title (kratko ime alineje), explanation in example,\n"
            ."- warnings: seznam opažanj in opozoril. Za vsako vrni title, explanation in example,\n"
            ."- next_ideas: seznam idej za naslednje srečanje. Za vsako vrni title, explanation in example,\n"
            ."- other_approaches: po eno konkretno priporočilo za delo s klientom iz vsake od teh psihoterapevtskih smeri: {$otherApproaches}. "
            ."Za vsako smer vrni approach (ime smeri, kot je navedeno), recommendation (kratko priporočilo v enem stavku), explanation in example.\n"
            ."explanation je daljša razlaga v 4 do 6 stavkih. Povej, kaj pomeni pri tem klientu, zakaj je na mestu in kaj terapevtka z njo doseže. Piši toplo, jasno in konkretno, brez splošnih učbeniških fraz.\n"
            ."example je konkreten primer iz tega srečanja: dobesedni stavek, ki bi ga terapevtka lahko rekla, ali natančen potek vaje v nekaj korakih.\n\n"
            .($previousContext ? "Kratek kontekst prejšnjih srečanj:\n{$previousContext}\n\n" : '')
            ."Zapisek / transkript srečanja:\n{$transcript}";

        $insightList = [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'title' => ['type' => 'STRING'],
                    'explanation' => ['type' => 'STRING'],
                    'example' => ['type' => 'STRING'],
                ],
                'required' => ['title', 'explanation', 'example'],
            ],
        ];

        $text = $this->generate(
            config('therapy.gemini.summary_model'),
            [['text' => $prompt]],
            [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'summary' => ['type' => 'STRING'],
                        'interventions' => $insightList,
                        'warnings' => $insightList,
                        'next_ideas' => $insightList,
                        'other_approaches' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'approach' => ['type' => 'STRING'],
                                    'recommendation' => ['type' => 'STRING'],
                                    'explanation' => ['type' => 'STRING'],
                                    'example' => ['type' => 'STRING'],
                                ],
                                'required' => ['approach', 'recommendation', 'explanation', 'example'],
                            ],
                        ],
                    ],
                    'required' => ['summary', 'interventions', 'warnings', 'next_ideas', 'other_approaches'],
                ],
            ],
        );

        $json = $this->extractJson($text);
        $interventions = $this->insightItems($json['interventions'] ?? []);
        $warnings = $this->insightItems($json['warnings'] ?? []);
        $nextIdeas = $this->insightItems($json['next_ideas'] ?? []);
        $approachInsights = $this->approachInsights($json['other_approaches'] ?? []);

        return [
            'summary' => $this->stringValue($json['summary'] ?? ''),
            'interventions' => $interventions['text'],
            'warnings' => $warnings['text'],
            'next_ideas' => $nextIdeas['text'],
            'insight_details' => [
                'interventions' => $interventions['items'],
                'warnings' => $warnings['items'],
                'next_ideas' => $nextIdeas['items'],
                'other_approaches' => $approachInsights['items'],
            ],
            'other_approaches' => $approachInsights['text'],
        ];
    }

    /**
     * Vse vrste terapije razen trenutne in splošne izbire "Drugo".
     *
     * @return list<string>
     */
    protected function otherApproaches(string $therapyType): array
    {
        return array_values(array_filter(
            config('therapy.therapy_types'),
            fn (string $type): bool => ! in_array($type, [$therapyType, 'Drugo'], true),
        ));
    }

    /**
     * @return array{text: string, items: list<array{title: string, explanation: string, example: string}>}
     */
    protected function approachInsights(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

        if (! is_array($value)) {
            return ['text' => '', 'items' => []];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_array($item) && (array_key_exists('approach', $item) || array_key_exists('recommendation', $item))) {
                $approach = $this->insightTitle($item['approach'] ?? '');
                $recommendation = $this->stringValue($item['recommendation'] ?? '');
                $title = trim($approach.($recommendation !== '' ? ': '.$recommendation : ''), ': ');
                $explanation = $this->stringValue($item['explanation'] ?? '');
                $example = $this->stringValue($item['example'] ?? '');
            } elseif (is_array($item)) {
                $title = $this->insightTitle($item['title'] ?? '');
                $explanation = $this->stringValue($item['explanation'] ?? '');
                $example = $this->stringValue($item['example'] ?? '');
            } elseif (is_scalar($item)) {
                $title = $this->insightTitle($item);
                $explanation = '';
                $example = '';
            } else {
                continue;
            }

            if ($title === '') {
                continue;
            }

            $items[] = [
                'title' => $title,
                'explanation' => $explanation,
                'example' => $example,
            ];
        }

        return [
            'text' => $this->bulletText(array_column($items, 'title')),
            'items' => $items,
        ];
    }

    /**
     * @return array{text: string, items: list<array{title: string, explanation: string, example: string}>}
     */
    protected function insightItems(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

        $items = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    $title = $this->insightTitle($item['title'] ?? '');
                    $explanation = $this->stringValue($item['explanation'] ?? '');
                    $example = $this->stringValue($item['example'] ?? '');
                } elseif (is_scalar($item)) {
                    $title = $this->insightTitle($item);
                    $explanation = '';
                    $example = '';
                } else {
                    continue;
                }

                if ($title === '') {
                    continue;
                }

                $items[] = [
                    'title' => $title,
                    'explanation' => $explanation,
                    'example' => $example,
                ];
            }
        }

        return [
            'text' => $this->bulletText(array_column($items, 'title')),
            'items' => $items,
        ];
    }

    protected function insightTitle(mixed $value): string
    {
        $title = $this->stringValue($value);
        $title = preg_replace('/^([-*•–—]|\d+[.)])\s+/u', '', $title) ?? $title;

        return trim($title);
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
