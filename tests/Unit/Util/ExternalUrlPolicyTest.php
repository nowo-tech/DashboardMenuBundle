<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Util;

use Nowo\DashboardMenuBundle\Util\ExternalUrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExternalUrlPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function urlProvider(): iterable
    {
        yield 'https' => ['https://example.com/a?b=1', true];
        yield 'http' => ['http://example.com', true];
        yield 'mailto' => ['mailto:info@example.com', true];
        yield 'tel' => ['tel:+34900000000', true];
        yield 'relative path' => ['/admin/page', true];
        yield 'fragment' => ['#top', true];
        yield 'protocol relative' => ['//cdn.example.com/x', true];
        yield 'javascript' => ['javascript:alert(1)', false];
        yield 'javascript uppercase' => ['JAVASCRIPT:alert(1)', false];
        yield 'javascript with control chars' => ["\x01java\nscript:alert(1)", false];
        yield 'data' => ['data:text/html,<script>alert(1)</script>', false];
        yield 'vbscript' => ['vbscript:msgbox(1)', false];
        yield 'file' => ['file:///etc/passwd', false];
    }

    #[DataProvider('urlProvider')]
    public function testIsAllowed(string $url, bool $expected): void
    {
        self::assertSame($expected, ExternalUrlPolicy::isAllowed($url));
    }

    public function testSanitize(): void
    {
        self::assertNull(ExternalUrlPolicy::sanitize(null));
        self::assertNull(ExternalUrlPolicy::sanitize('   '));
        self::assertNull(ExternalUrlPolicy::sanitize('javascript:alert(1)'));
        self::assertSame('https://example.com', ExternalUrlPolicy::sanitize(' https://example.com '));
    }
}
