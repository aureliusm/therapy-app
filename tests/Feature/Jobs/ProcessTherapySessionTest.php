<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessTherapySession;
use App\Models\TherapySession;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ProcessTherapySessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/*';

    public function test_stores_summary_bullets_and_explanations_without_themes(): void
    {
        config(['therapy.gemini.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            self::GEMINI_URL => Http::response($this->geminiText(json_encode([
                'summary' => 'Klient je govoril o službi.',
                'interventions' => [
                    ['title' => 'Validacija', 'explanation' => 'Potrditev občutkov klienta.', 'example' => 'Slišim, da te je to prizadelo.'],
                    ['title' => 'Delo z ego stanji', 'explanation' => 'Raziskava notranjih glasov.', 'example' => 'Kateri del tebe govori zdaj?'],
                ],
                'warnings' => [
                    ['title' => 'Utrujenost', 'explanation' => 'Tempo je bil prehiter.', 'example' => 'Naslednjič preveri spanje.'],
                ],
                'next_ideas' => [
                    ['title' => 'Raziskati odnos s šefom', 'explanation' => 'Odnos ostaja odprt.', 'example' => 'Vprašaj, kaj bi šefu povedal.'],
                ],
            ]))),
        ]);
        $session = TherapySession::factory()->processing()->create([
            'topics' => ['služba', 'meje'],
        ]);

        (new ProcessTherapySession($session->id))->handle(app(GeminiService::class));

        $session->refresh();
        $this->assertSame('ready', $session->status);
        $this->assertSame('Klient je govoril o službi.', $session->summary);
        $this->assertNull($session->topics);
        $this->assertSame("- Validacija\n- Delo z ego stanji", $session->interventions);
        $this->assertSame('- Utrujenost', $session->warnings);
        $this->assertSame('- Raziskati odnos s šefom', $session->next_ideas);
        $this->assertSame([
            'interventions' => [
                ['title' => 'Validacija', 'explanation' => 'Potrditev občutkov klienta.', 'example' => 'Slišim, da te je to prizadelo.'],
                ['title' => 'Delo z ego stanji', 'explanation' => 'Raziskava notranjih glasov.', 'example' => 'Kateri del tebe govori zdaj?'],
            ],
            'warnings' => [
                ['title' => 'Utrujenost', 'explanation' => 'Tempo je bil prehiter.', 'example' => 'Naslednjič preveri spanje.'],
            ],
            'next_ideas' => [
                ['title' => 'Raziskati odnos s šefom', 'explanation' => 'Odnos ostaja odprt.', 'example' => 'Vprašaj, kaj bi šefu povedal.'],
            ],
        ], $session->insight_details);
        Http::assertSent(function (Request $request): bool {
            $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text');
            $required = data_get($request->data(), 'generationConfig.responseSchema.required');

            return ! str_contains($prompt, 'topics')
                && ! str_contains($prompt, 'ključnih tem')
                && str_contains($prompt, 'explanation')
                && str_contains($prompt, 'example')
                && $required === ['summary', 'interventions', 'warnings', 'next_ideas', 'other_approaches'];
        });
    }

    public function test_plain_string_lists_store_bullets_without_explanations(): void
    {
        config(['therapy.gemini.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            self::GEMINI_URL => Http::response($this->geminiText(json_encode([
                'summary' => 'Povzetek',
                'interventions' => ['Validacija'],
                'warnings' => [],
                'next_ideas' => [],
            ]))),
        ]);
        $session = TherapySession::factory()->processing()->create();

        (new ProcessTherapySession($session->id))->handle(app(GeminiService::class));

        $session->refresh();
        $this->assertSame('- Validacija', $session->interventions);
        $this->assertSame([
            ['title' => 'Validacija', 'explanation' => '', 'example' => ''],
        ], $session->insight_details['interventions']);
    }

    public function test_stores_one_recommendation_per_other_therapy_approach(): void
    {
        config(['therapy.gemini.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            self::GEMINI_URL => Http::response($this->geminiText(json_encode([
                'summary' => 'Povzetek', 'interventions' => [], 'warnings' => [], 'next_ideas' => [],
                'other_approaches' => [
                    ['approach' => 'Geštalt terapija', 'recommendation' => 'Delo s praznim stolom.'],
                    ['approach' => 'Telesna terapija', 'recommendation' => 'Opazovanje dihanja.'],
                ],
            ]))),
        ]);
        $session = TherapySession::factory()->processing()->create(['therapy_type' => 'Transakcijska analiza (TA)']);

        (new ProcessTherapySession($session->id))->handle(app(GeminiService::class));

        $this->assertSame(
            "- Geštalt terapija: Delo s praznim stolom.\n- Telesna terapija: Opazovanje dihanja.",
            $session->refresh()->other_approaches,
        );
        Http::assertSent(function (Request $request): bool {
            $prompt = data_get($request->data(), 'contents.0.parts.0.text');
            $approachesLine = str($prompt)->after('- other_approaches:')->before("\n")->toString();

            return str_contains($approachesLine, 'Geštalt terapija, Telesna terapija, Integrativna')
                && ! str_contains($approachesLine, 'Transakcijska analiza')
                && ! str_contains($approachesLine, 'Drugo');
        });
    }

    public function test_sends_api_key_in_header_instead_of_url(): void
    {
        config(['therapy.gemini.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            self::GEMINI_URL => Http::response($this->geminiText(json_encode([
                'summary' => 'Povzetek', 'interventions' => [], 'warnings' => [], 'next_ideas' => [],
            ]))),
        ]);
        $session = TherapySession::factory()->processing()->create();

        (new ProcessTherapySession($session->id))->handle(app(GeminiService::class));

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-goog-api-key', 'test-key')
            && ! str_contains($request->url(), 'test-key'));
    }

    public function test_gemini_error_marks_session_failed_with_api_error_message(): void
    {
        config(['therapy.gemini.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            self::GEMINI_URL => Http::response(['error' => ['message' => 'API key not valid.']], 400),
        ]);
        $session = TherapySession::factory()->processing()->create();

        try {
            (new ProcessTherapySession($session->id))->handle(app(GeminiService::class));
            $this->fail('Job bi moral vreči izjemo.');
        } catch (RuntimeException) {
        }

        $session->refresh();
        $this->assertSame('failed', $session->status);
        $this->assertSame('Gemini napaka (400): API key not valid.', $session->error_message);
    }

    public function test_failed_callback_unlocks_session_stuck_in_processing(): void
    {
        $session = TherapySession::factory()->processing()->create();

        (new ProcessTherapySession($session->id))->failed(new RuntimeException('Job timed out.'));

        $session->refresh();
        $this->assertSame('failed', $session->status);
        $this->assertSame('Job timed out.', $session->error_message);
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiText(string $text): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
    }
}
