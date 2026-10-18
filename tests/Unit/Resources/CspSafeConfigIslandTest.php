<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the CSP-safe JSON config island contract (2.2.0): no inline assignment of
 * window.__nowoDashboardMenuConfig in dashboard templates, and a single island in _page_scripts.html.twig (2.7).
 */
final class CspSafeConfigIslandTest extends TestCase
{
    private const VIEWS = __DIR__ . '/../../../src/Resources/views/dashboard';

    public function testNoDashboardTemplateAssignsLegacyGlobal(): void
    {
        $files = glob(self::VIEWS . '/*.twig');
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            self::assertStringNotContainsString(
                '__nowoDashboardMenuConfig',
                (string) file_get_contents($file),
                basename($file) . ' must not set the legacy inline config global.',
            );
        }
    }

    public function testPageScriptsEmitSingleJsonIsland(): void
    {
        $scripts = (string) file_get_contents(self::VIEWS . '/_page_scripts.html.twig');

        self::assertSame(1, substr_count($scripts, 'type="application/json" id="nowo-dashboard-menu-config"'));
        self::assertStringContainsString('page_config', $scripts);
        self::assertStringContainsString('JSON_HEX_TAG', $scripts);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pageTemplates(): iterable
    {
        foreach (['index', 'show', 'show_items_reorder', 'item_form'] as $name) {
            yield $name => [$name . '.html.twig'];
        }
    }

    #[DataProvider('pageTemplates')]
    public function testPageTemplatesPassTheirConfigToThePageScripts(string $template): void
    {
        $content = (string) file_get_contents(self::VIEWS . '/' . $template);

        self::assertStringContainsString('_page_scripts.html.twig', $content);
        self::assertStringContainsString('page_config:', $content);
        self::assertStringNotContainsString('<script>', $content);
    }
}
