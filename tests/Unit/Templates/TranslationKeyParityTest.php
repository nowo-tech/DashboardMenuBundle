<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function array_diff;
use function array_keys;
use function basename;
use function dirname;
use function glob;
use function implode;
use function is_array;
use function preg_match;
use function preg_match_all;
use function sort;

/**
 * Every shipped locale defines exactly the same translation keys as the English catalogue
 * (no fallback to raw keys or English in the admin), and keeps the same %placeholders%.
 */
final class TranslationKeyParityTest extends TestCase
{
    private const DIR = '/src/Resources/translations';

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        foreach (glob(dirname(__DIR__, 3) . self::DIR . '/NowoDashboardMenuBundle.*.yaml') ?: [] as $file) {
            if (preg_match('/\.([a-zA-Z_]+)\.yaml$/', basename($file), $m) === 1 && $m[1] !== 'en') {
                yield $m[1] => [$m[1]];
            }
        }
    }

    #[DataProvider('locales')]
    public function testLocaleHasSameKeysAndPlaceholdersAsEnglish(string $locale): void
    {
        $en     = $this->load('en');
        $target = $this->load($locale);

        $missing = array_diff(array_keys($en), array_keys($target));
        $extra   = array_diff(array_keys($target), array_keys($en));

        self::assertSame([], $missing, $locale . ' misses keys: ' . implode(', ', $missing));
        self::assertSame([], $extra, $locale . ' has keys unknown to en: ' . implode(', ', $extra));

        foreach ($en as $key => $value) {
            preg_match_all('/%[a-zA-Z_]+%/', $value, $expected);
            preg_match_all('/%[a-zA-Z_]+%/', $target[$key], $actual);
            sort($expected[0]);
            sort($actual[0]);
            self::assertSame($expected[0], $actual[0], $locale . ' placeholders differ for ' . $key);
            if ($value !== '') {
                self::assertNotSame('', $target[$key], $locale . ' has an empty value for ' . $key);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function load(string $locale): array
    {
        $catalog = Yaml::parseFile(dirname(__DIR__, 3) . self::DIR . '/NowoDashboardMenuBundle.' . $locale . '.yaml');
        self::assertTrue(is_array($catalog));

        return self::flatten($catalog);
    }

    /**
     * @param array<mixed> $tree
     *
     * @return array<string, string>
     */
    private static function flatten(array $tree, string $prefix = ''): array
    {
        $out = [];
        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $out += self::flatten($value, $path);
            } else {
                $out[$path] = (string) $value;
            }
        }

        return $out;
    }
}
