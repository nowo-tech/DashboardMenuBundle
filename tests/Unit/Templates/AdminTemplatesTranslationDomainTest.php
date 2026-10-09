<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function strlen;

use const PREG_OFFSET_CAPTURE;

/**
 * Bundle message keys must be translated in the `NowoDashboardMenuBundle` domain: a bare `|trans`
 * looks the key up in the host's `messages` domain and prints it raw (e.g. `dashboard.yes`).
 * Templates that declare `trans_default_domain` are exempt. Literal English strings used as keys
 * ("Edit menu", "Update") are not translation keys at all.
 */
final class AdminTemplatesTranslationDomainTest extends TestCase
{
    private const DOMAIN = 'NowoDashboardMenuBundle';

    public function testBundleKeysAreTranslatedInTheBundleDomain(): void
    {
        $missing = [];
        foreach ($this->templates() as $path => $source) {
            if (str_contains($source, 'trans_default_domain')) {
                continue;
            }
            preg_match_all("~'(?:dashboard|form)\.[A-Za-z0-9_.]+'(?:\s*:\s*'(?:dashboard|form)\.[A-Za-z0-9_.]+'\))?\|trans~", $source, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[0] as [$match, $offset]) {
                $after = substr($source, $offset + strlen($match));
                if (!str_starts_with($after, '(') || !str_contains($this->balancedArguments($after), self::DOMAIN)) {
                    $missing[] = $path . ': ' . $match;
                }
            }
        }

        self::assertSame([], $missing, 'Bundle keys translated outside the bundle domain.');
    }

    public function testNoLiteralEnglishKeys(): void
    {
        $literal = [];
        foreach ($this->templates() as $path => $source) {
            if (preg_match("~'(?:Edit menu|New menu|Update|Create)'~", $source) === 1) {
                $literal[] = $path;
            }
        }

        self::assertSame([], $literal);
    }

    /**
     * @return iterable<string, string>
     */
    private function templates(): iterable
    {
        $root = dirname(__DIR__, 3) . '/src/Resources/views';
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
                yield substr($file->getPathname(), strlen($root) + 1) => (string) file_get_contents($file->getPathname());
            }
        }
    }

    private function balancedArguments(string $source): string
    {
        $depth = 0;
        for ($i = 0, $length = strlen($source); $i < $length; ++$i) {
            if ($source[$i] === '(') {
                ++$depth;
            } elseif ($source[$i] === ')' && --$depth === 0) {
                return substr($source, 0, $i + 1);
            }
        }

        return $source;
    }
}
