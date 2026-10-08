<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RequestLocaleResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SETG-3: pure precedence rules — user → X-Locale → tenant default → 'ar'.
 */
final class RequestLocaleResolverTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: mixed, 2: ?string, 3: string}>
     */
    public static function cases(): array
    {
        return [
            'nothing set → ar' => [null, null, null, 'ar'],
            'tenant default only' => [null, null, 'en', 'en'],
            'header beats tenant default' => [null, 'ar', 'en', 'ar'],
            'user beats header' => ['en', 'ar', 'ar', 'en'],
            'user beats tenant default' => ['ar', null, 'en', 'ar'],
            'header is trimmed and lowercased' => [null, ' EN ', null, 'en'],
            'unsupported header ignored' => [null, 'fr', 'en', 'en'],
            'region header ignored' => [null, 'en-US', null, 'ar'],
            'array header ignored' => [null, ['en'], null, 'ar'],
            'unsupported user locale ignored' => ['fr', 'en', null, 'en'],
            'unsupported tenant default ignored' => [null, null, 'de', 'ar'],
            'empty strings ignored' => ['', '', '', 'ar'],
        ];
    }

    #[DataProvider('cases')]
    public function test_pick_applies_the_documented_precedence(mixed $user, mixed $header, ?string $tenantDefault, string $expected): void
    {
        $this->assertSame($expected, RequestLocaleResolver::pick($user, $header, $tenantDefault));
    }

    public function test_normalize_never_echoes_raw_input(): void
    {
        $this->assertNull(RequestLocaleResolver::normalize('../../etc/passwd'));
        $this->assertNull(RequestLocaleResolver::normalize(42));
        $this->assertSame('ar', RequestLocaleResolver::normalize('AR'));
    }
}
