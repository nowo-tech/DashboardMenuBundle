<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\Doctrine\ExcludedEntityNamespaceDriver;
use Nowo\DashboardMenuBundle\Worker\DashboardMenuOwnEntityManagerSubscriber;
use Nowo\DashboardMenuBundle\Worker\UnitOfWorkBoundaries;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_unshift;
use function dirname;
use function is_array;
use function is_string;
use function sprintf;

/**
 * `nowo_dashboard_menu.doctrine.entity_manager` (2.18, architecture bar rule 6): maps the menu entities on that
 * manager only.
 *
 * The configured manager gets the attribute driver of the bundle entities; every other manager loses the bundle
 * namespace from its driver chain (DoctrineBundle's `auto_mapping`, the Flex default on the default manager, maps it
 * there too, and `getManagerForClass()` would answer the default manager) and gets an
 * {@see ExcludedEntityNamespaceDriver} for it instead, so a default driver of that chain cannot claim the entities
 * either (DoctrineBundle 3.4 `auto_discover_entities`). Only the public `addDriver()` of the
 * `doctrine.orm.<manager>_metadata_driver` chain is used, the extension point DoctrineBundle documents for other
 * metadata drivers. An unknown manager fails the build. Without the option nothing changes (the entities are mapped
 * by the host, usually through `auto_mapping`).
 *
 * 2.21.0 (REQ-RUNTIME-002 rule 4): when that manager is not the host's default manager, it is the bundle's own manager
 * and {@see DashboardMenuOwnEntityManagerSubscriber} clears it at the outermost unit-of-work boundaries. A manager that
 * is the host default is never cleared by the bundle (rule 7).
 */
final class DashboardMenuEntityManagerPass implements CompilerPassInterface
{
    public const ENTITY_NAMESPACE = 'Nowo\\DashboardMenuBundle\\Entity';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(DashboardMenuExtension::ENTITY_MANAGER_PARAMETER)) {
            return;
        }

        $name = $container->getParameter(DashboardMenuExtension::ENTITY_MANAGER_PARAMETER);
        if (!is_string($name) || $name === '') {
            return;
        }

        $managers = $container->hasParameter('doctrine.entity_managers') ? $container->getParameter('doctrine.entity_managers') : [];
        $managers = is_array($managers) ? $managers : [];
        if (!isset($managers[$name])) {
            throw new InvalidConfigurationException(sprintf('nowo_dashboard_menu.doctrine.entity_manager is "%s", but no such Doctrine entity manager is configured (known: "%s"). Add it under doctrine.orm.entity_managers or remove the option.', $name, implode('", "', array_keys($managers))));
        }

        foreach (array_keys($managers) as $manager) {
            $chainId = sprintf('doctrine.orm.%s_metadata_driver', $manager);
            if (!$container->hasDefinition($chainId)) {
                continue;
            }

            $chain = $container->getDefinition($chainId);
            $calls = [];
            foreach ($chain->getMethodCalls() as $call) {
                if ($call[0] === 'addDriver' && is_string($call[1][1] ?? null) && str_starts_with($call[1][1], self::ENTITY_NAMESPACE)) {
                    continue;
                }

                $calls[] = $call;
            }

            // First in the chain: a chain asks its namespace drivers in order, then its default driver.
            $driver = $manager === $name
                ? new Definition(AttributeDriver::class, [[dirname(__DIR__, 2) . '/Entity'], true])
                : new Definition(ExcludedEntityNamespaceDriver::class);
            array_unshift($calls, ['addDriver', [$driver, self::ENTITY_NAMESPACE]]);

            $chain->setMethodCalls($calls);
        }

        $this->registerOwnEntityManagerSubscriber($container, $name);
    }

    private function registerOwnEntityManagerSubscriber(ContainerBuilder $container, string $name): void
    {
        $default = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : null;
        if ($name === $default || !$container->has('doctrine')) {
            return;
        }

        // Only a manager the unit of work already built: a boundary never builds one.
        $container->register(DashboardMenuOwnEntityManagerSubscriber::class, DashboardMenuOwnEntityManagerSubscriber::class)
            ->setArguments([
                new Reference(UnitOfWorkBoundaries::class),
                new IteratorArgument([new Reference(sprintf('doctrine.orm.%s_entity_manager', $name), ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE)]),
                new Reference('doctrine'),
                $name,
            ])
            ->addTag('kernel.event_subscriber');
    }
}
