<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

/**
 * Converts full icon library names to short prefixes before rendering the menu
 * (e.g. "bootstrap-icons:house" → "bi:house" when map has bootstrap-icons => bi).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuIconNameResolver
{
    /**
     * @param array<string, string> $libraryPrefixMap Map library name => short prefix (e.g. ['bootstrap-icons' => 'bi'])
     */
    public function __construct(
        private array $libraryPrefixMap = [],
    ) {
    }

    /**
     * Converts an icon identifier to use the short library prefix when configured.
     * Example: "bootstrap-icons:house" with map ['bootstrap-icons' => 'bi'] → "bi:house".
     */
    public function resolve(?string $icon): ?string
    {
        if ($icon === null || $icon === '') {
            return $icon;
        }

        $colon = strpos($icon, ':');
        if ($colon === false) {
            return $icon;
        }

        $library = substr($icon, 0, $colon);
        // Config keys may be normalized (e.g. "bootstrap-icons" → "bootstrap_icons"), so try both
        $prefix = $this->libraryPrefixMap[$library]
            ?? $this->libraryPrefixMap[str_replace('-', '_', $library)]
            ?? null;
        if ($prefix === null) {
            return $icon;
        }

        return $prefix . substr($icon, $colon);
    }
}
