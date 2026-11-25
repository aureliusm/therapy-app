<?php

namespace Tests\Feature\Pages;

use App\Models\Client;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SessionEditPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saving_session_removes_themes_and_keeps_explanations_for_unchanged_lines(): void
    {
        $client = Client::factory()->create();
        $session = TherapySession::factory()->for($client)->create([
            'topics' => ['stara tema'],
            'interventions' => "- Validacija\n- Opustitev",
            'warnings' => '- Utrujenost',
            'next_ideas' => '- Naslednji korak',
            'insight_details' => [
                'interventions' => [
                    ['title' => 'Validacija', 'explanation' => 'Potrditev doživljanja.', 'example' => 'Slišim, kako težko ti je.'],
                    ['title' => 'Opustitev', 'explanation' => 'Stara razlaga.', 'example' => 'Star primer.'],
                ],
                'warnings' => [
                    ['title' => 'Utrujenost', 'explanation' => 'Pozor na tempo.', 'example' => 'Vprašaj po spanju.'],
                ],
                'next_ideas' => [
                    ['title' => 'Naslednji korak', 'explanation' => 'Nadaljevanje.', 'example' => 'Vaja med tednom.'],
                ],
            ],
        ]);

        Livewire::test('pages::sessions.edit', ['client' => $client, 'session' => $session])
            ->assertDontSee('ključne teme')
            ->set('interventions', "- Validacija\n- Nova")
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('sessions.show', [$client, $session]));

        $session->refresh();
        $this->assertNull($session->topics);
        $this->assertSame([
            ['title' => 'Validacija', 'explanation' => 'Potrditev doživljanja.', 'example' => 'Slišim, kako težko ti je.'],
            ['title' => 'Nova', 'explanation' => '', 'example' => ''],
        ], $session->insight_details['interventions']);
        $this->assertSame([
            ['title' => 'Utrujenost', 'explanation' => 'Pozor na tempo.', 'example' => 'Vprašaj po spanju.'],
        ], $session->insight_details['warnings']);
    }
}
