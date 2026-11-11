<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Symfony\Component\HttpFoundation\Request;

use function array_key_exists;
use function get_debug_type;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * Translates the HttpFoundation request and the legacy `mixed` permission context into the bundle-owned value objects
 * of the request-free ports. The only place where that translation happens.
 *
 * Since 2.20, {@see fromRequest()} is a supported public API (covered by the BC promise): hosts use it in their own
 * HTTP edge code and tests to build exactly the {@see MenuRequestContext} the bundle hands to its ports, instead of
 * calling the constructor by hand. The class is an HTTP-edge adapter, not a port: it may take a Symfony `Request`
 * (REQ-ARCH-001 rule 3 concerns ports and value objects). {@see droppedValues()} and {@see permissionContext()}
 * remain internal.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuRequestContextFactory
{
    /**
     * The framework-free context of a request, as the bundle builds it for its ports: the route (`_route`), the scalar
     * route parameters (`_route_params`), the path info, the locale, the query parameters and the host (no headers,
     * cookies or session). The bundle passes the main request; pass the same in tests. Public API since 2.20.
     *
     * @param Request $request the request the menu is rendered for
     */
    public static function fromRequest(Request $request): MenuRequestContext
    {
        $route  = $request->attributes->get('_route');
        $params = $request->attributes->get('_route_params', []);

        /** @var array<string, mixed> $query */
        $query = $request->query->all();

        return new MenuRequestContext(
            is_string($route) ? $route : null,
            is_array($params) ? $params : [],
            $request->getPathInfo(),
            $request->getLocale(),
            $query,
            $request->getHost(),
        );
    }

    /**
     * What {@see permissionContext()} drops from a legacy context: an object (other than a request or a
     * {@see MenuPermissionContext}), or array entries that are not plain values.
     *
     * @internal
     *
     * @return list<string> one description per dropped value, empty when nothing is dropped
     */
    public static function droppedValues(mixed $context): array
    {
        if ($context === null || is_scalar($context) || $context instanceof MenuPermissionContext || $context instanceof Request) {
            return [];
        }
        if (!is_array($context)) {
            return ['object ' . get_debug_type($context)];
        }
        $kept    = (new MenuPermissionContext(null, $context))->attributes;
        $dropped = [];
        foreach ($context as $name => $value) {
            if (!is_string($name) || !array_key_exists($name, $kept)) {
                $dropped[] = 'attribute ' . (is_string($name) ? $name : '#' . $name) . ' (' . get_debug_type($value) . ')';
            }
        }

        return $dropped;
    }

    /**
     * The value object for a legacy permission context:
     * - a {@see MenuPermissionContext} is returned as is;
     * - a {@see Request} becomes the request part (what the deprecated ports received by default);
     * - an array becomes the attributes (plain values only);
     * - a scalar becomes the attribute `value`;
     * - null or any other object gives an empty context (the checker decides with no input).
     *
     * @internal
     */
    public static function permissionContext(mixed $context): MenuPermissionContext
    {
        return match (true) {
            $context instanceof MenuPermissionContext => $context,
            $context instanceof Request               => new MenuPermissionContext(self::fromRequest($context)),
            is_array($context)                        => new MenuPermissionContext(null, $context),
            is_scalar($context)                       => new MenuPermissionContext(null, ['value' => $context]),
            default                                   => new MenuPermissionContext(),
        };
    }
}
