<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use function in_array;

/**
 * Resolves the effective locale for menu labels from the request locale
 * and the bundle's configured locales / default_locale.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuLocaleResolver
{
    /**
     * @param list<string> $locales enabled locales (empty = use request locale as-is)
     * @param string|null $defaultLocale fallback when request locale is not in locales
     */
    public function __construct(
        private array $locales,
        private ?string $defaultLocale = null,
    ) {
    }

    /**
     * Returns the locale to use for loading menu labels.
     * If locales are configured: request locale if in list, else default_locale or first of locales.
     * If locales are empty: returns request locale unchanged.
     */
    public function resolveLocale(string $requestLocale): string
    {
        if ($this->locales === []) {
            return $requestLocale;
        }

        $requestLocale = trim($requestLocale);
        if ($requestLocale !== '' && in_array($requestLocale, $this->locales, true)) {
            return $requestLocale;
        }

        if ($this->defaultLocale !== null && $this->defaultLocale !== '') {
            return $this->defaultLocale;
        }

        return $this->locales[0];
    }

    /**
     * @return list<string>
     */
    public function getLocales(): array
    {
        return $this->locales;
    }
}
