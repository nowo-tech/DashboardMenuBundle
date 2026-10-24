<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_keys;
use function is_string;
use function str_starts_with;

/**
 * Keeps the bundle controllers out of Symfony's {@code routing.controllers} import.
 *
 * Autoconfiguration tags every service whose class carries {@code #[Route]} with
 * {@code routing.controller}, and an application that imports {@code routing.controllers}
 * (the Symfony 7.3+ default in {@code config/routes.yaml}) would then load the dashboard
 * routes at the root ({@code /}, {@code /{id}}, {@code /menu/new}…) even when the dashboard is
 * disabled. The bundle routes must come only from its own route files, which honour the
 * {@code prefix} and the {@code dashboard} / {@code api} switches.
 *
 * Runs before {@code RoutingControllerPass} (priority 0) and after autoconfiguration (priority 100).
 */
final class ExcludeControllersFromRoutingPass implements CompilerPassInterface
{
    private const TAG = 'routing.controller';

    private const CONTROLLER_NAMESPACE = 'Nowo\\DashboardMenuBundle\\Controller\\';

    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $id) {
            $definition = $container->getDefinition($id);
            $class      = $definition->getClass();
            if (!is_string($class) || !str_starts_with($class, self::CONTROLLER_NAMESPACE)) {
                continue;
            }

            // @igor-ignore - Compile-time DI definition wiring; runs once at container build
            $definition->clearTag(self::TAG);
        }
    }
}
