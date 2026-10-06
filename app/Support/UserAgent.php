<?php

namespace App\Support;

/**
 * Tiny, dependency-free user-agent → "Browser · OS" label. Deliberately avoids
 * heavy parser packages (Hostinger-light). Good enough for a devices list.
 */
final class UserAgent
{
    public static function describe(?string $ua): string
    {
        $ua = trim((string) $ua);
        if ($ua === '') {
            return 'Dispositivo desconocido';
        }

        $browser = self::browser($ua);
        $os = self::os($ua);

        return trim($browser.($os ? ' · '.$os : '')) ?: 'Dispositivo desconocido';
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') || str_contains($ua, 'Edge') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',       // check after Edge/Opera (they embed Chrome)
            str_contains($ua, 'Safari/') => 'Safari',       // check after Chrome
            default => 'Navegador',
        };
    }

    private static function os(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows NT') => 'Windows',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Linux') => 'Linux',
            default => '',
        };
    }
}
