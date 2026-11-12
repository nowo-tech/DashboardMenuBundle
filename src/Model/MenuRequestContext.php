<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Model;

use Nowo\DashboardMenuBundle\Service\MenuCodeContextResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemContextCurrentMatcherInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemContextLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuRequestContextFactory;

use function array_key_exists;
use function is_scalar;
use function is_string;
use function preg_match;

/**
 * Framework-free view of the request a menu is rendered for: route, route parameters, path, locale, query and host.
 *
 * The bundle builds it from the main request (the URL the user opened, not a forward / sub-request) and passes it to
 * the request-free ports ({@see MenuCodeContextResolverInterface},
 * {@see MenuItemContextLinkResolverInterface},
 * {@see MenuItemContextCurrentMatcherInterface}) and inside
 * {@see MenuPermissionContext}. It holds no headers, cookies or session data.
 *
 * To build one from a Symfony request (an HTTP edge of your own, or a test), use the public
 * {@see MenuRequestContextFactory::fromRequest()} (2.20): it applies the same
 * rules as the bundle.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuRequestContext
{
    /** @var array<string, bool|float|int|string|null> */
    public array $routeParams;

    /**
     * @param string|null $route current route name (`_route`), or null outside a routed request
     * @param array<array-key, mixed> $routeParams route parameters (`_route_params`); only scalar and null values
     *                                             with string keys are kept
     * @param string $path path info (no query string), e.g. `/admin/users`
     * @param string $locale request locale
     * @param array<string, mixed> $query query parameters (plain strings and arrays of them)
     * @param string $host host name, without the port
     */
    public function __construct(
        public ?string $route,
        array $routeParams,
        public string $path,
        public string $locale,
        public array $query = [],
        public string $host = '',
    ) {
        $params = [];
        foreach ($routeParams as $name => $value) {
            if (is_string($name) && ($value === null || is_scalar($value))) {
                $params[$name] = $value;
            }
        }
        $this->routeParams = $params;
    }

    /**
     * Dump form: query values whose key looks like a secret (token, signature, password, key, session, auth, code)
     * are shown as `[redacted]`, so a context that reaches a dump or a log does not carry a one-time token.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $query = [];
        foreach ($this->query as $name => $value) {
            $query[$name] = preg_match('/(token|secret|pass|signature|sig$|key|session|auth|jwt|^code$)/i', (string) $name) === 1 ? '[redacted]' : $value;
        }

        return [
            'route'       => $this->route,
            'routeParams' => $this->routeParams,
            'path'        => $this->path,
            'locale'      => $this->locale,
            'query'       => $query,
            'host'        => $this->host,
        ];
    }

    /**
     * One route parameter.
     *
     * @param string $name parameter name
     *
     * @return bool|float|int|string|null the value, or null when absent
     */
    public function routeParam(string $name): bool|float|int|string|null
    {
        return $this->routeParams[$name] ?? null;
    }

    /**
     * Whether a route parameter is present.
     *
     * @param string $name parameter name
     */
    public function hasRouteParam(string $name): bool
    {
        return array_key_exists($name, $this->routeParams);
    }
}
