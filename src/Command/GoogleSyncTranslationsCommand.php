<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function html_entity_decode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function rawurlencode;
use function sort;
use function sprintf;
use function strtolower;
use function stream_context_create;
use function trim;
use function uasort;

#[AsCommand(
    name: 'nowo_dashboard_menu:translations:google-sync',
    description: 'Review missing translation keys and optionally auto-translate from a base locale using Google Translate API.',
)]
final class GoogleSyncTranslationsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('base-locale', null, InputOption::VALUE_REQUIRED, 'Base locale to compare against', 'en')
            ->addOption('target-locales', null, InputOption::VALUE_REQUIRED, 'Comma-separated target locales (default: all except base)')
            ->addOption('translate-missing', null, InputOption::VALUE_NONE, 'Translate missing keys with Google API')
            ->addOption('write', null, InputOption::VALUE_NONE, 'Write translated values back to files')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'Google Translate API key (or env GOOGLE_TRANSLATE_API_KEY)')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit with failure when missing keys are found');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io            = new SymfonyStyle($input, $output);
        $baseLocale    = strtolower(trim((string) $input->getOption('base-locale')));
        $translate     = (bool) $input->getOption('translate-missing');
        $write         = (bool) $input->getOption('write');
        $strict        = (bool) $input->getOption('strict');
        $targetLocales = $this->parseTargetLocales((string) $input->getOption('target-locales'));

        $translationDir = dirname(__DIR__) . '/Resources/translations';
        $files          = glob($translationDir . '/NowoDashboardMenuBundle.*.yaml') ?: [];
        if ($files === []) {
            $io->error('No translation files found in bundle resources.');

            return Command::FAILURE;
        }

        $byLocale = [];
        foreach ($files as $file) {
            if (!preg_match('/NowoDashboardMenuBundle\.([^.]+)\.yaml$/', $file, $m)) {
                continue;
            }
            $locale        = strtolower($m[1]);
            $data          = Yaml::parseFile($file);
            $byLocale[$locale] = [
                'file' => $file,
                'data' => is_array($data) ? $data : [],
            ];
        }

        if (!isset($byLocale[$baseLocale])) {
            $io->error(sprintf('Base locale "%s" does not exist.', $baseLocale));

            return Command::FAILURE;
        }

        if ($targetLocales === []) {
            $targetLocales = array_values(array_filter(array_keys($byLocale), static fn (string $locale): bool => $locale !== $baseLocale));
        }
        $targetLocales = array_values(array_filter($targetLocales, static fn (string $locale): bool => $locale !== $baseLocale && isset($byLocale[$locale])));
        if ($targetLocales === []) {
            $io->warning('No valid target locales found.');

            return Command::SUCCESS;
        }

        $baseFlat  = $this->flatten((array) $byLocale[$baseLocale]['data']);
        $baseKeys  = array_keys($baseFlat);
        $missingBy = [];

        foreach ($targetLocales as $locale) {
            $targetFlat   = $this->flatten((array) $byLocale[$locale]['data']);
            $missingKeys  = [];
            foreach ($baseKeys as $key) {
                if (!array_key_exists($key, $targetFlat)) {
                    $missingKeys[] = $key;
                }
            }
            $missingBy[$locale] = $missingKeys;
        }

        uasort($missingBy, static fn (array $a, array $b): int => count($b) <=> count($a));
        $io->section(sprintf('Base locale: %s', $baseLocale));
        foreach ($missingBy as $locale => $keys) {
            $io->text(sprintf('- %s: %d missing key(s)', $locale, count($keys)));
        }

        $totalMissing = 0;
        foreach ($missingBy as $keys) {
            $totalMissing += count($keys);
        }
        if ($totalMissing === 0) {
            $io->success('No missing translation keys found.');

            return Command::SUCCESS;
        }

        if (!$translate) {
            $io->note('Review-only mode. Use --translate-missing to generate translations.');

            return $strict ? Command::FAILURE : Command::SUCCESS;
        }

        $apiKey = trim((string) ($input->getOption('api-key') ?: ($_ENV['GOOGLE_TRANSLATE_API_KEY'] ?? $_SERVER['GOOGLE_TRANSLATE_API_KEY'] ?? '')));
        if ($apiKey === '') {
            $io->error('Google API key required. Use --api-key or GOOGLE_TRANSLATE_API_KEY.');

            return Command::FAILURE;
        }

        $translatedCount = 0;
        foreach ($missingBy as $locale => $keys) {
            if ($keys === []) {
                continue;
            }

            $targetData = (array) $byLocale[$locale]['data'];
            foreach ($keys as $key) {
                $source = $baseFlat[$key] ?? null;
                if (!is_string($source) || trim($source) === '') {
                    continue;
                }
                $translated = $this->translateWithGoogle($apiKey, $source, $baseLocale, $locale);
                $this->setNestedValue($targetData, explode('.', $key), $translated);
                ++$translatedCount;
                $io->text(sprintf('Translated [%s] %s', $locale, $key));
            }

            $byLocale[$locale]['data'] = $targetData;
            if ($write) {
                $yaml = Yaml::dump($targetData, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                file_put_contents((string) $byLocale[$locale]['file'], $yaml);
            }
        }

        if ($write) {
            $io->success(sprintf('Translated %d key(s) and wrote updated files.', $translatedCount));
        } else {
            $io->warning(sprintf('Translated %d key(s) in memory only. Re-run with --write to persist.', $translatedCount));
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function parseTargetLocales(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $locales = array_map(static fn (string $v): string => strtolower(trim($v)), explode(',', $raw));
        $locales = array_values(array_filter($locales, static fn (string $v): bool => $v !== ''));
        $locales = array_values(array_unique($locales));
        sort($locales);

        return $locales;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $path = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += $this->flatten($value, $path);
                continue;
            }
            $flat[$path] = $value;
        }

        return $flat;
    }

    /**
     * @param array<string, mixed> $arr
     * @param list<string>         $path
     */
    private function setNestedValue(array &$arr, array $path, string $value): void
    {
        $cursor = &$arr;
        foreach ($path as $i => $part) {
            if ($i === count($path) - 1) {
                $cursor[$part] = $value;
                break;
            }
            if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
                $cursor[$part] = [];
            }
            $cursor = &$cursor[$part];
        }
    }

    private function translateWithGoogle(string $apiKey, string $text, string $sourceLocale, string $targetLocale): string
    {
        $endpoint = sprintf('https://translation.googleapis.com/language/translate/v2?key=%s', rawurlencode($apiKey));
        $payload  = json_encode([
            'q'      => $text,
            'source' => $sourceLocale,
            'target' => $targetLocale,
            'format' => 'text',
        ], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 20,
            ],
        ]);

        $raw = file_get_contents($endpoint, false, $context);
        if (!is_string($raw) || trim($raw) === '') {
            return $text;
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return $text;
        }
        $translated = $json['data']['translations'][0]['translatedText'] ?? null;
        if (!is_string($translated) || $translated === '') {
            return $text;
        }

        return html_entity_decode($translated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

