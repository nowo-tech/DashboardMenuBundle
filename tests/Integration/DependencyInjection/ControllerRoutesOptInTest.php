<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DependencyInjection;

use Nowo\DashboardMenuBundle\Controller\Dashboard\MenuDashboardController;
use Nowo\DashboardMenuBundle\Tests\Kernel\HostControllersRoutesKernel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

use function class_exists;
use function is_string;
use function sprintf;
use function str_starts_with;

/**
 * REQ-SF-004 rule 7: the bundle routes are opt-in. The controller services are not autoconfigured, so an
 * application importing only {@code routing.controllers} (Symfony 7.3+ default) gets none of the bundle routes
 * (they would mount the dashboard at {@code /}, {@code /{id}}, {@code /menu/new}… and the API without its prefix).
 */
final class ControllerRoutesOptInTest extends TestCase
{
    private ?HostControllersRoutesKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testRoutingControllersAloneRegistersNoBundleRoute(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }

        $router = $this->bootKernel()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach ($router->getRouteCollection()->all() as $name => $route) {
            $controller = $route->getDefault('_controller');
            self::assertFalse(str_starts_with($name, 'nowo_dashboard_menu'), sprintf('Route "%s" must only exist when the host imports the bundle route files.', $name));
            self::assertFalse(
                is_string($controller) && str_starts_with($controller, 'Nowo\\DashboardMenuBundle\\Controller\\'),
                sprintf('Route "%s" points to a bundle controller without the bundle route import.', $name),
            );
        }
    }

    /**
     * Without autoconfiguration the dashboard controller (an AbstractController) still gets its service locator
     * through the explicit container.service_subscriber tag.
     */
    public function testDashboardControllerKeepsItsServiceLocator(): void
    {
        $controller = $this->bootKernel()->get(MenuDashboardController::class);
        self::assertInstanceOf(MenuDashboardController::class, $controller);

        $locator = (new ReflectionProperty($controller, 'container'))->getValue($controller);
        self::assertInstanceOf(ContainerInterface::class, $locator);
        self::assertTrue($locator->has('router'));
        self::assertTrue($locator->has('parameter_bag'));
    }

    private function bootKernel(): SymfonyContainerInterface
    {
        $this->kernel = new HostControllersRoutesKernel('test', true);
        $this->kernel->boot();

        return $this->kernel->getContainer();
    }
}
