<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Command;

use Nowo\DashboardMenuBundle\Command\GoogleSyncTranslationsCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

use function dirname;
use function file_put_contents;
use function is_file;
use function is_string;
use function unlink;

/**
 * REQ-TEST-003: the review / translate / write flow, with a throwaway locale file (zz) next to the real ones and a
 * `data:` URL as the translate endpoint (no network).
 */
final class GoogleSyncTranslationsCommandTest extends TestCase
{
    private const OK_ENDPOINT = 'data://application/json,{"data":{"translations":[{"translatedText":"T-&amp;-%s"}]}}';

    private string $zz;

    protected function setUp(): void
    {
        $this->zz = dirname(__DIR__, 3) . '/src/Resources/translations/NowoDashboardMenuBundle.zz.yaml';
        unset($_SERVER['GOOGLE_TRANSLATE_API_KEY']);
    }

    protected function tearDown(): void
    {
        if (is_file($this->zz)) {
            unlink($this->zz);
        }
        unset($_SERVER['GOOGLE_TRANSLATE_API_KEY']);
    }

    public function testAnUnknownBaseLocaleFails(): void
    {
        $tester = new CommandTester(new GoogleSyncTranslationsCommand());

        self::assertSame(Command::FAILURE, $tester->execute(['--base-locale' => 'qq']));
        self::assertStringContainsString('does not exist', $tester->getDisplay());
    }

    public function testNoValidTargetLocaleIsAWarning(): void
    {
        $tester = new CommandTester(new GoogleSyncTranslationsCommand());

        self::assertSame(Command::SUCCESS, $tester->execute(['--target-locales' => 'qq, EN']));
        self::assertStringContainsString('No valid target locales', $tester->getDisplay());
    }

    public function testCompleteLocalesReportNothingMissing(): void
    {
        $tester = new CommandTester(new GoogleSyncTranslationsCommand());

        self::assertSame(Command::SUCCESS, $tester->execute(['--target-locales' => 'es,fr,es']));
        self::assertStringContainsString('No missing translation keys', $tester->getDisplay());
    }

    public function testReviewOnlyModeListsMissingKeysAndStrictFails(): void
    {
        file_put_contents($this->zz, "dashboard:\n  placeholder: x\n");
        $tester = new CommandTester(new GoogleSyncTranslationsCommand());

        self::assertSame(Command::SUCCESS, $tester->execute(['--target-locales' => 'zz']));
        self::assertStringContainsString('zz:', $tester->getDisplay());
        self::assertStringContainsString('Review-only mode', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['--target-locales' => 'zz', '--strict' => true]));
    }

    public function testTranslatingNeedsAnApiKey(): void
    {
        file_put_contents($this->zz, "dashboard:\n  placeholder: x\n");
        $tester = new CommandTester(new GoogleSyncTranslationsCommand(self::OK_ENDPOINT));

        self::assertSame(Command::FAILURE, $tester->execute(['--target-locales' => 'zz', '--translate-missing' => true]));
        self::assertStringContainsString('API key required', $tester->getDisplay());
    }

    public function testTranslatesInMemoryWithoutWriting(): void
    {
        file_put_contents($this->zz, "dashboard:\n  placeholder: x\n");
        $tester = new CommandTester(new GoogleSyncTranslationsCommand(self::OK_ENDPOINT));

        $_SERVER['GOOGLE_TRANSLATE_API_KEY'] = 'secret';
        self::assertSame(Command::SUCCESS, $tester->execute(['--target-locales' => 'zz', '--translate-missing' => true, '--timeout' => '0']));

        self::assertStringContainsString('Translated [zz]', $tester->getDisplay());
        self::assertStringContainsString('in memory only', $tester->getDisplay());
        self::assertSame("dashboard:\n  placeholder: x\n", file_get_contents($this->zz));
    }

    public function testWritesTheTranslatedValuesBackWithTheDecodedText(): void
    {
        file_put_contents($this->zz, "dashboard:\n  placeholder: x\n");
        $tester = new CommandTester(new GoogleSyncTranslationsCommand(self::OK_ENDPOINT));

        self::assertSame(Command::SUCCESS, $tester->execute(['--target-locales' => 'zz', '--translate-missing' => true, '--write' => true, '--api-key' => 'k e y']));

        self::assertStringContainsString('wrote updated files', $tester->getDisplay());
        $data = Yaml::parseFile($this->zz);
        self::assertSame('x', $data['dashboard']['placeholder']);
        self::assertContains('T-&-k e y', $this->leaves($data), 'the HTML entities of the answer are decoded (the key travels URL-encoded and the data: wrapper decodes it)');
    }

    #[DataProvider('unusableAnswers')]
    public function testAnUnusableAnswerKeepsTheSourceText(string $endpoint): void
    {
        file_put_contents($this->zz, "dashboard:\n  placeholder: x\n");
        $tester = new CommandTester(new GoogleSyncTranslationsCommand($endpoint));

        self::assertSame(Command::SUCCESS, $tester->execute(['--target-locales' => 'zz', '--translate-missing' => true, '--write' => true, '--api-key' => 'k']));

        $en = $this->leaves(Yaml::parseFile(dirname($this->zz) . '/NowoDashboardMenuBundle.en.yaml'));
        $zz = $this->leaves(Yaml::parseFile($this->zz));
        self::assertNotEmpty(array_intersect($en, $zz));
        self::assertNotContains('T-k', $zz);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableAnswers(): iterable
    {
        yield 'empty answer' => ['data://text/plain,%s'];
        yield 'not JSON' => ['data://text/plain,not-json-%s'];
        yield 'no translation in the answer' => ['data://application/json,{"data":{"x":"%s"}}'];
        yield 'empty translation' => ['data://application/json,{"data":{"translations":[{"translatedText":""}]}}?%s'];
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private function leaves(array $data): array
    {
        $out = [];
        array_walk_recursive($data, static function (mixed $value) use (&$out): void {
            if (is_string($value)) {
                $out[] = $value;
            }
        });

        return $out;
    }
}
