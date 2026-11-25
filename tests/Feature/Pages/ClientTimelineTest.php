<?php

namespace Tests\Feature\Pages;

use App\Models\Client;
use App\Models\TherapySession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ClientTimelineTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_timeline_shows_the_full_summary_without_other_fields(): void
    {
        $summary = 'Začetek povzetka. '.str_repeat('Nadaljevanje pogovora o mejah. ', 8).'Konec povzetka ostane viden.';
        $client = Client::factory()->create();
        TherapySession::factory()->for($client)->create([
            'summary' => $summary,
            'status' => 'ready',
            'topics' => ['skrita-tema-ki-ne-sme-biti-vidna'],
            'interventions' => '- Validacija',
            'warnings' => '- Utrujenost',
            'next_ideas' => '- Naslednji korak',
            'insight_details' => [
                'interventions' => [
                    ['title' => 'Validacija', 'explanation' => 'Potrditev doživljanja.', 'example' => 'Slišim, kako težko ti je.'],
                ],
                'warnings' => [
                    ['title' => 'Utrujenost', 'explanation' => 'Tempo je bil prehiter.', 'example' => 'Vprašaj po spanju.'],
                ],
                'next_ideas' => [
                    ['title' => 'Naslednji korak', 'explanation' => 'Nadaljevanje ostaja odprto.', 'example' => 'Dogovorita se za vajo.'],
                ],
            ],
        ]);

        $response = $this->get(route('clients.show', $client));

        $response->assertOk();
        $response->assertSee($summary);
        $response->assertDontSee('uporabljene intervence');
        $response->assertDontSee('opozorila in opažanja');
        $response->assertDontSee('za naslednje srečanje');
        $response->assertDontSee('Validacija');
        $response->assertDontSee('Potrditev doživljanja.');
        $response->assertDontSee('skrita-tema-ki-ne-sme-biti-vidna');
        $response->assertDontSee('ključne teme');
    }
}
