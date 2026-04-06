<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Attribute;

use Attribute;

/**
 * Label for the dashboard "Link resolver (service)" dropdown on menu items.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class MenuLinkResolverLabel
{
    public function __construct(
        public string $label,
    ) {
    }
}
