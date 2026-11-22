<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Support;

use Nowo\DashboardMenuBundle\Service\MenuBadgeProviderInterface;
use RuntimeException;

use function array_key_exists;

/**
 * Test provider: counts and records the calls, answers from a key => value map (a RuntimeException value is thrown).
 */
final class RecordingBadgeProvider implements MenuBadgeProviderInterface
{
    public int $calls = 0;

    /** @var list<string> */
    public array $keys = [];

    /**
     * @param array<string, int|RuntimeException|null> $values answers by item key
     * @param int|RuntimeException|null $default answer for a key not in the map
     */
    public function __construct(public array $values = [], public int|RuntimeException|null $default = null)
    {
    }

    public function badge(string $itemKey): ?int
    {
        ++$this->calls;
        $this->keys[] = $itemKey;
        $value        = array_key_exists($itemKey, $this->values) ? $this->values[$itemKey] : $this->default;
        if ($value instanceof RuntimeException) {
            throw $value;
        }

        return $value;
    }
}
