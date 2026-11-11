<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

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
 * there too, and `getManagerForClass()` would answer the default manager). An unknown manager fails the build. Without
 * the option nothing changes (the entities are mapped by the host, usually through `auto_mapping`).
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

            if ($manager === $name) {
                $calls[] = ['addDriver', [new Definition(AttributeDriver::class, [[dirname(__DIR__, 2) . '/Entity'], true]), self::ENTITY_NAMESPACE]];
            }

            $chain->setMethodCalls($calls);
        }
    }
}
