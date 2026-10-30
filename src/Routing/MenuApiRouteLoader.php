<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Routing;

use Nowo\DashboardMenuBundle\Controller\Api\MenuApiController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

use function trim;

/**
 * Imports the JSON menu API route (`nowo_dashboard_menu_api`) declared by the `#[Route]` attribute on
 * {@see MenuApiController} and applies the `api` configuration: no route when `api.enabled` is false,
 * otherwise `GET {api.path_prefix}/{code}`.
 *
 * Thin wrapper only (REQ-SF-004): the route itself (name, path, requirements, methods) lives on the controller;
 * this loader adds the configurable prefix. Loaded through `@NowoDashboardMenuBundle/Resources/config/routes.yaml`
 * (type `nowo_dashboard_menu_api`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuApiRouteLoader extends Loader
{
    public const TYPE = 'nowo_dashboard_menu_api';

    public const ROUTE_NAME = 'nowo_dashboard_menu_api';

    public function __construct(
        private readonly bool $enabled,
        private readonly string $pathPrefix,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::TYPE;
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();

        if (!$this->enabled) {
            return $routes;
        }

        /** @var RouteCollection $imported the attribute loader always returns a collection for a class */
        $imported = $this->import(MenuApiController::class, 'attribute');
        $routes->addCollection($imported);
        $prefix = trim($this->pathPrefix, '/');
        if ($prefix !== '') {
            $routes->addPrefix('/' . $prefix);
        }

        return $routes;
    }
}
