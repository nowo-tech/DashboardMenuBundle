<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Fixtures;

use Nowo\DashboardMenuBundle\Attribute\AsMenuBadgeProvider;
use Nowo\DashboardMenuBundle\Service\MenuBadgeProviderInterface;

/**
 * Attributed provider used to check the autoconfiguration of #[AsMenuBadgeProvider].
 */
#[AsMenuBadgeProvider(item: ['a', 'b'])]
final class MenuBadgeProviderFixture implements MenuBadgeProviderInterface
{
    public function badge(string $itemKey): ?int
    {
        return $itemKey === 'a' ? 1 : null;
    }
}
