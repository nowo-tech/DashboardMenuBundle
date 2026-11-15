<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Model;

/**
 * Cards of a hub section (2.22): consecutive loose links share one block without a group; each nested section of the
 * menu (a group such as "Email") is its own block, titled by that section's label.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuHubBlock
{
    /**
     * @param string|null $group label of the nested menu section, or null for loose links
     * @param list<MenuHubCard> $cards
     */
    public function __construct(
        public ?string $group,
        public array $cards,
    ) {
    }
}
