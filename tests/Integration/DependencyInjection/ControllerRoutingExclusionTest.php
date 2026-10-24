<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DependencyInjection;

use Nowo\DashboardMenuBundle\Controller\Api\MenuApiController;
use Nowo\DashboardMenuBundle\Controller\Dashboard\MenuDashboardController;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use PHPUnit\Framework\TestCase;
use Reflector;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\DependencyInjection\RoutingControllerPass;

/**
 * Regression: an application importing {@code routing.controllers} must not get the bundle
 * controllers (they would mount the dashboard at {@code /}, {@code /{id}}, {@code /menu/new}…).
 * Uses Symfony's own {@code #[Route]} autoconfiguration and {@code RoutingControllerPass}.
 */
final class ControllerRoutingExclusionTest extends TestCase
{
    public function testRoutingControllersImportSkipsBundleControllers(): void
    {
        $container = new ContainerBuilder();
        $container->registerAttributeForAutoconfiguration(Route::class, static function (ChildDefinition $definition, Route $attribute, Reflector $reflection): void {
            $definition->addTag('controller.service_arguments')->addTag('routing.controller');
        });
        $container->setDefinition('routing.loader.attribute.services', (new Definition('stdClass', [[]]))->setPublic(true));
        $container->setDefinition('app.controller', (new Definition(ApplicationController::class))->setAutoconfigured(true)->setPublic(true));
        foreach ([MenuApiController::class, MenuDashboardController::class] as $class) {
            $container->setDefinition($class, (new Definition($class))->setAutoconfigured(true)->setPublic(true));
        }

        (new NowoDashboardMenuBundle())->build($container);
        $container->addCompilerPass(new RoutingControllerPass());
        $container->compile();

        self::assertSame([ApplicationController::class], $container->getDefinition('routing.loader.attribute.services')->getArgument(0));
        self::assertTrue($container->getDefinition(MenuDashboardController::class)->hasTag('controller.service_arguments'));
    }
}

/** @internal */
#[Route('/home', name: 'app_home')]
final class ApplicationController
{
    public function __invoke(): void
    {
    }
}
