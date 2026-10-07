<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the CSP-safe JSON config island contract (2.2.0): no inline assignment of
 * window.__nowoDashboardMenuConfig in dashboard templates, and a single island in base.html.twig.
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

    public function testBaseTemplateEmitsSingleJsonIsland(): void
    {
        $base = (string) file_get_contents(self::VIEWS . '/base.html.twig');

        self::assertSame(1, substr_count($base, 'type="application/json" id="nowo-dashboard-menu-config"'));
        self::assertStringContainsString('nowo_dashboard_menu_page_config', $base);
        self::assertStringContainsString('JSON_HEX_TAG', $base);
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
    public function testPageTemplatesProvidePageConfigAtTopLevel(string $template): void
    {
        $content = (string) file_get_contents(self::VIEWS . '/' . $template);

        self::assertStringContainsString('{% set nowo_dashboard_menu_page_config', $content);
        self::assertStringNotContainsString('<script>', $content);
    }
}
