<?php

namespace Tests\Unit\Models;

use App\Models\TherapySession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TherapySessionTest extends TestCase
{
    /**
     * @return array<string, array{?string, list<string>}>
     */
    public static function bulletTexts(): array
    {
        return [
            'empty' => [null, []],
            'dash bullets' => ["- prva\n- druga", ['prva', 'druga']],
            'mixed markers and blank lines' => ["• prva\r\n\r\n1. druga\n* tretja", ['prva', 'druga', 'tretja']],
            'plain lines' => ["prva\ndruga", ['prva', 'druga']],
            'placeholder dash only' => ['—', []],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('bulletTexts')]
    public function test_bullet_items_strips_markers_and_empty_lines(?string $text, array $expected): void
    {
        $this->assertSame($expected, TherapySession::bulletItems($text));
    }

    public function test_align_insights_keeps_explanation_when_title_stays(): void
    {
        $aligned = TherapySession::alignInsights("- Validacija\n- Nova", [
            ['title' => 'Validacija', 'explanation' => 'Razlaga', 'example' => 'Primer'],
            ['title' => 'Stara', 'explanation' => 'Gre stran', 'example' => 'Star primer'],
        ]);

        $this->assertSame([
            ['title' => 'Validacija', 'explanation' => 'Razlaga', 'example' => 'Primer'],
            ['title' => 'Nova', 'explanation' => '', 'example' => ''],
        ], $aligned);
    }

    public function test_align_insights_drops_details_when_text_is_empty(): void
    {
        $aligned = TherapySession::alignInsights(null, [
            ['title' => 'Validacija', 'explanation' => 'Razlaga', 'example' => 'Primer'],
        ]);

        $this->assertSame([], $aligned);
    }
}
