<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\Service\DefaultMenuCodeResolver;
use Nowo\DashboardMenuBundle\Service\MenuCodeContextResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Picks the menu code resolver the Twig functions and the JSON API call: the host's
 * {@see MenuCodeContextResolverInterface} when it aliased that interface to its own service, otherwise whatever
 * {@see MenuCodeResolverInterface} points to (the bundle default, or a host override from before 2.19).
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuCodeResolverPass implements CompilerPassInterface
{
    public const RESOLVER_ID = 'nowo_dashboard_menu.internal.menu_code_resolver';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasAlias(self::RESOLVER_ID)) {
            return;
        }

        $contextTarget = $container->hasAlias(MenuCodeContextResolverInterface::class)
            ? (string) $container->getAlias(MenuCodeContextResolverInterface::class)
            : ($container->hasDefinition(MenuCodeContextResolverInterface::class) ? MenuCodeContextResolverInterface::class : DefaultMenuCodeResolver::class);

        $container->setAlias(
            self::RESOLVER_ID,
            $contextTarget !== DefaultMenuCodeResolver::class ? MenuCodeContextResolverInterface::class : MenuCodeResolverInterface::class,
        )->setPublic(false);
    }
}
