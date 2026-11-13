<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\DependencyInjection\Compiler;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\DashboardMenuEntityManagerPass;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\Doctrine\ExcludedEntityNamespaceDriver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class DashboardMenuEntityManagerPassTest extends TestCase
{
    public function testWithoutTheOptionNothingChanges(): void
    {
        $container = $this->container(null);
        (new DashboardMenuEntityManagerPass())->process($container);
        (new DashboardMenuEntityManagerPass())->process(new ContainerBuilder());

        self::assertCount(3, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
        self::assertCount(0, $container->getDefinition('doctrine.orm.dashboard_menu_metadata_driver')->getMethodCalls());
    }

    public function testMapsTheEntitiesOnTheConfiguredManagerOnly(): void
    {
        $container = $this->container('dashboard_menu');
        (new DashboardMenuEntityManagerPass())->process($container);

        // Other managers: the host's driver for the namespace is gone; a driver that maps nothing comes first, before
        // the host's drivers and before any default driver (DoctrineBundle 3.4 auto_discover_entities).
        $default = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertSame([['addDriver', DashboardMenuEntityManagerPass::ENTITY_NAMESPACE], ['addDriver', 'App\\Entity'], ['setDefaultDriver', null]], array_map(static fn (array $call): array => [$call[0], $call[1][1] ?? null], $default));
        self::assertInstanceOf(Definition::class, $default[0][1][0]);
        self::assertSame(ExcludedEntityNamespaceDriver::class, $default[0][1][0]->getClass());
        $bundle = $container->getDefinition('doctrine.orm.dashboard_menu_metadata_driver')->getMethodCalls();
        self::assertCount(1, $bundle);
        self::assertInstanceOf(Definition::class, $bundle[0][1][0]);
        self::assertSame(AttributeDriver::class, $bundle[0][1][0]->getClass());
        self::assertSame(DashboardMenuEntityManagerPass::ENTITY_NAMESPACE, $bundle[0][1][1]);

        (new DashboardMenuEntityManagerPass())->process($container);
        self::assertCount(1, $container->getDefinition('doctrine.orm.dashboard_menu_metadata_driver')->getMethodCalls(), 'idempotent');
        self::assertCount(3, $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls(), 'idempotent');
    }

    public function testUnknownManagerFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"reports", but no such Doctrine entity manager');
        (new DashboardMenuEntityManagerPass())->process($this->container('reports'));
    }

    public function testNoManagersAtAllFailsTheBuild(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(DashboardMenuExtension::ENTITY_MANAGER_PARAMETER, 'dashboard_menu');

        $this->expectException(InvalidConfigurationException::class);
        (new DashboardMenuEntityManagerPass())->process($container);
    }

    private function container(?string $name): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(DashboardMenuExtension::ENTITY_MANAGER_PARAMETER, $name);
        $container->setParameter('doctrine.entity_managers', [
            'default'        => 'doctrine.orm.default_entity_manager',
            'dashboard_menu' => 'doctrine.orm.dashboard_menu_entity_manager',
            'no_chain'       => 'doctrine.orm.no_chain_entity_manager',
        ]);
        $container->setDefinition('doctrine.orm.default_metadata_driver', (new Definition())
            ->addMethodCall('addDriver', [new Reference('attribute'), 'App\\Entity'])
            ->addMethodCall('addDriver', [new Reference('attribute'), DashboardMenuEntityManagerPass::ENTITY_NAMESPACE])
            ->addMethodCall('setDefaultDriver', [new Reference('auto_discover')]));
        $container->setDefinition('doctrine.orm.dashboard_menu_metadata_driver', new Definition());

        return $container;
    }
}
