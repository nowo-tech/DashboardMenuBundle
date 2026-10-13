<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Routing;

use Nowo\DashboardMenuBundle\Controller\Api\MenuApiController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

use function trim;

/**
 * Registers the JSON menu API route (`nowo_dashboard_menu_api`) from the `api` configuration:
 * no route when `api.enabled` is false, otherwise `GET {api.path_prefix}/{code}`.
 *
 * Loaded through `@NowoDashboardMenuBundle/Resources/config/routes.yaml` (type `nowo_dashboard_menu_api`).
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

        $prefix = trim($this->pathPrefix, '/');
        $path   = ($prefix === '' ? '' : '/' . $prefix) . '/{code}';

        $routes->add(self::ROUTE_NAME, new Route(
            $path,
            ['_controller' => MenuApiController::class],
            ['code' => '[a-zA-Z0-9_-]+'],
            [],
            '',
            [],
            ['GET'],
        ));

        return $routes;
    }
}
