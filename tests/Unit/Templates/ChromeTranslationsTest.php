<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function dirname;
use function is_string;

/**
 * Strings added for the admin chrome hooks (2.3.0) exist, non-empty, in every shipped locale.
 */
final class ChromeTranslationsTest extends TestCase
{
    private const KEYS = ['table_code', 'table_name', 'table_items', 'table_context', 'no_permission_keys', 'unanimous', 'non_unanimous', 'position_short'];

    public function testEveryLocaleDefinesTheChromeKeys(): void
    {
        $files = glob(dirname(__DIR__, 3) . '/src/Resources/translations/NowoDashboardMenuBundle.*.yaml') ?: [];
        self::assertCount(31, $files);

        $missing = [];
        foreach ($files as $file) {
            $dashboard = Yaml::parseFile($file)['dashboard'] ?? [];
            foreach (self::KEYS as $key) {
                if (!is_string($dashboard[$key] ?? null) || $dashboard[$key] === '') {
                    $missing[] = basename($file) . ': dashboard.' . $key;
                }
            }
        }

        self::assertSame([], $missing);
    }
}
