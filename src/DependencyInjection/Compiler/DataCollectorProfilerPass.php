<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Drops the dashboard menu data collector when the profiler is not available.
 *
 * The collector (registered in dev) requires the {@code profiler} service, which only exists when
 * {@code framework.profiler} is enabled. That cannot be checked in the extension (the FrameworkBundle
 * definitions are not visible there), so it is done here; consumers reference the collector as an
 * optional (null-on-invalid) dependency.
 */
final class DataCollectorProfilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(DashboardMenuDataCollector::class) || $container->has('profiler')) {
            return;
        }

        $container->removeDefinition(DashboardMenuDataCollector::class);
    }
}
