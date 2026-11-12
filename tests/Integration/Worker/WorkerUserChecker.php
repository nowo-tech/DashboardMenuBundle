<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Worker;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface;

use function in_array;
use function is_string;

/**
 * Host permission checker of the worker tests: `alice` may see `reports`, `bob` may see `billing`, items without keys
 * are public; the user comes from the context attributes the controller passes.
 */
final class WorkerUserChecker implements MenuItemContextPermissionCheckerInterface
{
    public const GRANTS = ['alice' => ['reports'], 'bob' => ['billing']];

    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
    {
        if ($item->permissionKeys === []) {
            return true;
        }

        $user = $context->attributes['user'] ?? null;
        if (!is_string($user)) {
            return false;
        }

        foreach ($item->permissionKeys as $key) {
            if (in_array($key, self::GRANTS[$user] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
}
