<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\PermissionCheckerPass;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class NowoDashboardMenuBundleTest extends TestCase
{
    public function testBuildAddsPermissionCheckerPass(): void
    {
        $container = new ContainerBuilder();
        $bundle    = new NowoDashboardMenuBundle();
        $bundle->build($container);

        $passes = $container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses();
        $found  = false;
        foreach ($passes as $pass) {
            if ($pass instanceof PermissionCheckerPass) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'PermissionCheckerPass should be registered');
    }

    public function testGetContainerExtensionReturnsDashboardMenuExtension(): void
    {
        $bundle    = new NowoDashboardMenuBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(DashboardMenuExtension::class, $extension);
        self::assertSame('nowo_dashboard_menu', $extension->getAlias());
    }

    public function testGetContainerExtensionReturnsSameInstanceOnMultipleCalls(): void
    {
        $bundle = new NowoDashboardMenuBundle();
        self::assertSame($bundle->getContainerExtension(), $bundle->getContainerExtension());
    }
}
