<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Area;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function explode;
use function in_array;
use function is_string;

/**
 * Grants the roles listed in the `X-Roles` header of the main request (comma separated). No state of its own.
 */
final readonly class AreaTestAuthorizationChecker implements AuthorizationCheckerInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function isGranted(mixed $attribute, mixed $subject = null, mixed $accessDecision = null): bool
    {
        $roles = $this->requestStack->getMainRequest()?->headers->get('X-Roles');

        return is_string($roles) && is_string($attribute) && in_array($attribute, explode(',', $roles), true);
    }
}
