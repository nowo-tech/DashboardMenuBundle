<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Attribute;

use Attribute;
use Nowo\DashboardMenuBundle\Service\MenuBadgeProviderInterface;

use function is_array;

/**
 * Registers a {@see MenuBadgeProviderInterface} for one or more menu item keys
 * (the route name of the item). Repeatable; autoconfigured, no service tag needed.
 *
 *     #[AsMenuBadgeProvider(item: 'customers_questions')]
 *     #[AsMenuBadgeProvider(item: ['orders_open', 'orders_late'])]
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsMenuBadgeProvider
{
    public const TAG = 'nowo_dashboard_menu.menu_badge_provider';

    /**
     * @param list<string>|string $item the item key(s) this provider answers for
     */
    public function __construct(public string|array $item)
    {
    }

    /**
     * @return list<string>
     */
    public function items(): array
    {
        return is_array($this->item) ? $this->item : [$this->item];
    }
}
