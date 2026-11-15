<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class GeminiService
{
    public function transcribe(string $absolutePath, string $mimeType): string
    {
        $chunks = $this->chunkAudio($absolutePath, $mimeType);
        $parts = [];

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

            if ($chunkPath !== $absolutePath && is_file($chunkPath)) {
                @unlink($chunkPath);
            }

            if ($index < count($chunks) - 1) {
                sleep(2);
            }
        }

        return trim(implode("\n\n", array_filter($parts)));
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
            ."Vrni SAMO veljaven JSON z naslednjimi ključi:\n"
            ."{\n"
            ."  \"summary\": \"povzetek približno pol strani A4\",\n"
            ."  \"topics\": [\"tema 1\", \"tema 2\"],\n"
            ."  \"interventions\": \"uporabljene intervence\",\n"
            ."  \"warnings\": \"opozorila in opažanja\",\n"
            ."  \"next_ideas\": \"ideje in intervence za naslednje srečanje\"\n"
            ."}\n\n"
            .($previousContext ? "Kratek kontekst prejšnjih srečanj:\n{$previousContext}\n\n" : '')
            ."Zapisek / transkript srečanja:\n{$transcript}";

        $text = $this->generate(
            config('therapy.gemini.summary_model'),
            [['text' => $prompt]],
            ['responseMimeType' => 'application/json'],
        );

        $json = $this->extractJson($text);

        return [
            'summary' => (string) ($json['summary'] ?? ''),
            'topics' => array_values(array_filter(array_map('strval', $json['topics'] ?? []))),
            'interventions' => (string) ($json['interventions'] ?? ''),
            'warnings' => (string) ($json['warnings'] ?? ''),
            'next_ideas' => (string) ($json['next_ideas'] ?? ''),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $parts
     * @param  array<string, mixed>  $generationConfig
     */
    protected function generate(string $model, array $parts, array $generationConfig = []): string
    {
        $apiKey = config('therapy.gemini.api_key');

        if (! filled($apiKey)) {
            throw new RuntimeException('Manjka GEMINI_API_KEY v .env datoteki.');
        }

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
        ];

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        $response = Http::timeout(180)
            ->acceptJson()
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                $payload,
            );

        if ($response->failed()) {
            throw new RuntimeException('Gemini napaka: '.$response->body());
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! filled($text)) {
            throw new RuntimeException('Gemini ni vrnil besedila.');
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

        if (! $this->ffmpegAvailable() || ! Str::contains($mimeType, ['webm', 'ogg', 'mp3', 'mpeg', 'wav', 'mp4', 'm4a'])) {
            return [$absolutePath];
        }

        $dir = sys_get_temp_dir().'/therapy-audio-'.Str::random(8);
        mkdir($dir);

        $pattern = $dir.'/chunk_%03d.webm';
        $result = Process::timeout(120)->run([
            'ffmpeg',
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

        $chunks = collect(glob($dir.'/chunk_*.webm') ?: [])
            ->sort()
            ->values()
            ->all();

        return $chunks !== [] ? $chunks : [$absolutePath];
    }

    protected function ffmpegAvailable(): bool
    {
        return Process::run(['which', 'ffmpeg'])->successful();
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
