<?php

namespace Tests\Feature\Pages;

use App\Models\Client;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SessionShowPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_session_page_hides_themes_and_ships_explanations_with_each_note(): void
    {
        $client = Client::factory()->create();
        $session = TherapySession::factory()->for($client)->create([
            'summary' => 'Povzetek srečanja.',
            'status' => 'ready',
            'topics' => ['skrita-tema-ki-ne-sme-biti-vidna'],
            'interventions' => '- Validacija',
            'other_approaches' => '- Geštalt terapija: Delo s praznim stolom.',
            'insight_details' => [
                'interventions' => [
                    ['title' => 'Validacija', 'explanation' => 'Potrditev doživljanja.', 'example' => 'Slišim, kako težko ti je.'],
                ],
                'other_approaches' => [
                    [
                        'title' => 'Geštalt terapija: Delo s praznim stolom.',
                        'explanation' => 'Prazni stol odpre pogovor, ki je ostal nedokončan.',
                        'example' => 'Postavi stol predse in povej, kaj ti je ostalo na jeziku.',
                    ],
                ],
            ],
        ]);

        $response = $this->get(route('sessions.show', [$client, $session]));

        $response->assertOk();
        $response->assertSee('Povzetek srečanja.');
        $response->assertSee('data-insight-title="Validacija"', false);
        $response->assertSee('Potrditev doživljanja.');
        $response->assertSee('Slišim, kako težko ti je.');
        $response->assertSee('data-insight-title="Geštalt terapija: Delo s praznim stolom."', false);
        $response->assertSee('Prazni stol odpre pogovor, ki je ostal nedokončan.');
        $response->assertSee('Postavi stol predse in povej, kaj ti je ostalo na jeziku.');
        $response->assertDontSee('skrita-tema-ki-ne-sme-biti-vidna');
        $response->assertDontSee('ključne teme');
    }
}
