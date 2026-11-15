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

    public function test_stores_summary_and_list_fields_as_bullets_from_gemini_response(): void
    {
        config(['therapy.gemini.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            self::GEMINI_URL => Http::response($this->geminiText(json_encode([
                'summary' => 'Klient je govoril o službi.',
                'topics' => ['služba', 'meje'],
                'interventions' => ['Validacija', 'Delo z ego stanji'],
                'warnings' => ['Utrujenost'],
                'next_ideas' => ['Raziskati odnos s šefom'],
            ]))),
        ]);
        $session = TherapySession::factory()->processing()->create();

        (new ProcessTherapySession($session->id))->handle(app(GeminiService::class));

        $session->refresh();
        $this->assertSame('ready', $session->status);
        $this->assertSame('Klient je govoril o službi.', $session->summary);
        $this->assertSame(['služba', 'meje'], $session->topics);
        $this->assertSame("- Validacija\n- Delo z ego stanji", $session->interventions);
        $this->assertSame('- Utrujenost', $session->warnings);
        $this->assertSame('- Raziskati odnos s šefom', $session->next_ideas);
    }

    public function test_sends_api_key_in_header_instead_of_url(): void
    {
        config(['therapy.gemini.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            self::GEMINI_URL => Http::response($this->geminiText(json_encode([
                'summary' => 'Povzetek', 'topics' => [], 'interventions' => [], 'warnings' => [], 'next_ideas' => [],
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
