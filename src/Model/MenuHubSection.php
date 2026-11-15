<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Model;

/**
 * One heading of a hub page (2.22): a top-level section of the area's menu tree. Top-level links outside any section
 * are gathered in a section without a label.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuHubSection
{
    /**
     * @param string|null $label the menu section's label, or null for top-level links
     * @param list<MenuHubBlock> $blocks
     */
    public function __construct(
        public ?string $label,
        public array $blocks,
    ) {
    }
}
