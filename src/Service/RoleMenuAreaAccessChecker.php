<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuArea;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Default {@see MenuAreaAccessCheckerInterface} (2.22): an area without `roles` is granted to everyone; otherwise any
 * of its roles must be granted. Each role is passed to isGranted() with the area as subject, so a voter attribute
 * works as well as a role. Without SecurityBundle, an area with roles is never granted (fail closed).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class RoleMenuAreaAccessChecker implements MenuAreaAccessCheckerInterface
{
    public function __construct(
        private ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {
    }

    public function canAccessArea(MenuArea $area): bool
    {
        if ($area->roles === []) {
            return true;
        }
        if (!$this->authorizationChecker instanceof AuthorizationCheckerInterface) {
            return false;
        }
        foreach ($area->roles as $role) {
            if ($this->authorizationChecker->isGranted($role, $area)) {
                return true;
            }
        }

        return false;
    }
}
