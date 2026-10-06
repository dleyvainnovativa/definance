<?php

namespace Tests\Unit;

use App\Support\UserAgent;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'chrome macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36', 'Chrome · macOS'],
            'edge windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36 Edg/120.0', 'Edge · Windows'],
            'safari iOS' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1 Version/17.0 Mobile/15E148 Safari/604.1', 'Safari · iOS'],
            'firefox linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0', 'Firefox · Linux'],
            'chrome android' => ['Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36', 'Chrome · Android'],
            'empty' => ['', 'Dispositivo desconocido'],
        ];
    }

    /** @dataProvider cases */
    public function test_it_describes_user_agents(string $ua, string $expected): void
    {
        $this->assertSame($expected, UserAgent::describe($ua));
    }
}
