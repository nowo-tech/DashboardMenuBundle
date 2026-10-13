<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Util;

use function in_array;
use function preg_match;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * Scheme allowlist for menu item external URLs rendered in `href` (blocks `javascript:`, `data:`, `vbscript:`, …).
 *
 * Allowed: `http`, `https`, `mailto`, `tel` and scheme-less (relative) URLs. Whitespace and control characters
 * are ignored when detecting the scheme, because browsers strip them (e.g. `java\tscript:`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ExternalUrlPolicy
{
    /** @var list<string> */
    public const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** Schemes accepted by the dashboard form (Assert\Url). */
    public const FORM_PROTOCOLS = ['http', 'https'];

    /**
     * Whether the URL may be rendered in a link `href`.
     *
     * @param string $url Raw external URL
     */
    public static function isAllowed(string $url): bool
    {
        $normalized = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $url);
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $matches) !== 1) {
            return true;
        }

        return in_array(strtolower($matches[1]), self::ALLOWED_SCHEMES, true);
    }

    /**
     * Returns the trimmed URL when allowed, or null when empty or using a disallowed scheme.
     *
     * @param string|null $url Raw external URL
     */
    public static function sanitize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        return $url !== '' && self::isAllowed($url) ? $url : null;
    }
}
