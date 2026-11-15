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
}
