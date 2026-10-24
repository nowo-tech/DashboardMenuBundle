<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\Controller\Api\MenuApiController;
use Nowo\DashboardMenuBundle\Controller\Dashboard\MenuDashboardController;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\ExcludeControllersFromRoutingPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class ExcludeControllersFromRoutingPassTest extends TestCase
{
    public function testProcessRemovesRoutingControllerTagFromBundleControllers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(MenuApiController::class, (new Definition(MenuApiController::class))->addTag('routing.controller')->addTag('controller.service_arguments'));
        $container->setDefinition(MenuDashboardController::class, (new Definition(MenuDashboardController::class))->addTag('routing.controller'));

        (new ExcludeControllersFromRoutingPass())->process($container);

        self::assertFalse($container->getDefinition(MenuApiController::class)->hasTag('routing.controller'));
        self::assertTrue($container->getDefinition(MenuApiController::class)->hasTag('controller.service_arguments'));
        self::assertFalse($container->getDefinition(MenuDashboardController::class)->hasTag('routing.controller'));
    }

    public function testProcessKeepsApplicationControllersAndUntypedDefinitions(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.controller', (new Definition('App\Controller\HomeController'))->addTag('routing.controller'));
        $container->setDefinition('untyped', (new Definition())->addTag('routing.controller'));
        $container->setDefinition('untagged', new Definition(MenuApiController::class));

        (new ExcludeControllersFromRoutingPass())->process($container);

        self::assertTrue($container->getDefinition('app.controller')->hasTag('routing.controller'));
        self::assertTrue($container->getDefinition('untyped')->hasTag('routing.controller'));
        self::assertFalse($container->getDefinition('untagged')->hasTag('routing.controller'));
    }
}
