<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use function in_array;
use function is_array;
use function is_string;

/**
 * Normalizes nowo_dashboard_menu.locales: a literal list, or the value of a container parameter / env var
 * ("%app.locales%", "%kernel.enabled_locales%", "%env(json:APP_LOCALES)%", "%env(csv:APP_LOCALES)%").
 *
 * Used at build time (literal list or build-time parameter) and as a service factory when the list comes from an
 * env var, which is only known at runtime.
 */
final class ConfiguredLocales
{
    public const FALLBACK_LOCALE = 'en';

    /**
     * @return list<string> non-empty locale codes, in order, without duplicates
     */
    public static function normalize(mixed $locales): array
    {
        if (is_string($locales)) {
            $decoded = str_starts_with(ltrim($locales), '[') ? json_decode($locales, true) : null;
            $locales = is_array($decoded) ? $decoded : explode(',', $locales);
        }
        if (!is_array($locales)) {
            return [];
        }

        $list = [];
        foreach ($locales as $locale) {
            if (!is_string($locale)) {
                continue;
            }
            $locale = trim($locale);
            if ($locale !== '' && !in_array($locale, $list, true)) {
                $list[] = $locale;
            }
        }

        return $list;
    }

    /**
     * The configured default_locale, else the first configured locale, else {@see FALLBACK_LOCALE}.
     */
    public static function defaultLocale(mixed $locales, ?string $defaultLocale = null): string
    {
        if ($defaultLocale !== null && trim($defaultLocale) !== '') {
            return trim($defaultLocale);
        }

        return self::normalize($locales)[0] ?? self::FALLBACK_LOCALE;
    }
}
