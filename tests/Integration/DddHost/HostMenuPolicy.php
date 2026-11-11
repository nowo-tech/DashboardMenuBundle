<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use function in_array;
use function str_starts_with;

/**
 * The host's access policy, shared by the legacy (entity + Request + mixed) adapters and the DDD (value object)
 * adapters: only the key `reports` is granted; any other key is unknown and refused (deny by default).
 */
final class HostMenuPolicy
{
    public const GRANTED = ['reports'];

    /**
     * @param list<string> $keys
     */
    public static function allows(array $keys, bool $unanimous, ?string $route): bool
    {
        // The policy also reads the request: nothing keyed is shown outside the admin area.
        if ($keys === []) {
            return true;
        }
        if ($route === null || !str_starts_with($route, 'admin_')) {
            return false;
        }
        foreach ($keys as $key) {
            $granted = in_array($key, self::GRANTED, true);
            if ($unanimous && !$granted) {
                return false;
            }
            if (!$unanimous && $granted) {
                return true;
            }
        }

        return $unanimous;
    }

    public static function menuCode(string $hint, ?string $route): string
    {
        return $hint === 'nav' && $route !== null && str_starts_with($route, 'admin_') ? 'sidebar' : $hint;
    }

    public static function isCurrent(?string $itemRoute, ?string $route): bool
    {
        return $itemRoute === 'admin_index' && $route !== null && str_starts_with($route, 'admin_');
    }

    /**
     * @return list<array{label: string, href: string, position: int}>
     */
    public static function projects(?string $tenant): array
    {
        return [['label' => 'Projects of ' . ($tenant ?? 'nobody'), 'href' => '/projects/' . ($tenant ?? 'none'), 'position' => 0]];
    }
}
