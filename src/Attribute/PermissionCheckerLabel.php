<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Attribute;

use Attribute;

/**
 * Use this attribute on a class that implements MenuPermissionCheckerInterface
 * to set the label shown in the dashboard "Permission checker" dropdown.
 *
 * If omitted, the bundle uses the class constant DASHBOARD_LABEL (if defined) or the service id.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class PermissionCheckerLabel
{
    public function __construct(
        public readonly string $label,
    ) {
    }
}
