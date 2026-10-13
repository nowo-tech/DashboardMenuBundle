<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber;
use Nowo\DashboardMenuBundle\Security\AllowAllDashboardMenuAccessChecker;
use Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Enforces SecurityBundle for dashboard UI unless allow_unauthenticated is true (REQ-UI-002).
 *
 * The dashboard access subscriber is always registered (fail closed) because hosts may import
 * routes_dashboard.yaml regardless of configuration: with dashboard.enabled false it answers dashboard
 * routes with 404, and an empty security.access_roles without a custom checker denies access.
 */
final class DashboardMenuSecurityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(Configuration::ALIAS . '.dashboard.enabled')) {
            return;
        }

        if (!(bool) $container->getParameter(Configuration::ALIAS . '.dashboard.enabled')) {
            // Dashboard routes may still be imported: 404 them (the access checker is never consulted).
            $this->registerSubscriber($container, new Definition(AllowAllDashboardMenuAccessChecker::class), false);

            return;
        }

        $allowUnauthenticated = (bool) $container->getParameter(Configuration::ALIAS . '.security.allow_unauthenticated');
        $hasSecurity          = $container->has('security.authorization_checker');

        if (!$hasSecurity && !$allowUnauthenticated) {
            throw new InvalidConfigurationException('nowo_dashboard_menu dashboard requires symfony/security-bundle (security.authorization_checker), or set nowo_dashboard_menu.security.allow_unauthenticated: true (dev/demo only — never in production).');
        }

        if ($allowUnauthenticated) {
            $this->registerSubscriber($container, new Definition(AllowAllDashboardMenuAccessChecker::class), true);

            return;
        }

        // Empty access_roles without a custom checker is fail-closed via ConfigurableDashboardMenuAccessChecker.
        $this->registerSubscriber($container, new Reference(DashboardMenuAccessCheckerInterface::class), true);
    }

    private function registerSubscriber(ContainerBuilder $container, Definition|Reference $accessChecker, bool $dashboardEnabled): void
    {
        if ($container->hasDefinition(DashboardAccessSubscriber::class)) {
            return;
        }

        $container->register(DashboardAccessSubscriber::class, DashboardAccessSubscriber::class)
            ->setArgument('$accessChecker', $accessChecker)
            ->setArgument('$dashboardEnabled', $dashboardEnabled)
            ->addTag('kernel.event_subscriber');
    }
}
