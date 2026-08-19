<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber;
use Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Enforces SecurityBundle for dashboard UI unless allow_unauthenticated is true (REQ-UI-002).
 */
final class DashboardMenuSecurityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(Configuration::ALIAS . '.dashboard.enabled')) {
            return;
        }
        if (!(bool) $container->getParameter(Configuration::ALIAS . '.dashboard.enabled')) {
            return;
        }

        $allowUnauthenticated = (bool) $container->getParameter(Configuration::ALIAS . '.security.allow_unauthenticated');
        $hasSecurity          = $container->has('security.authorization_checker');

        if (!$hasSecurity && !$allowUnauthenticated) {
            throw new InvalidConfigurationException('nowo_dashboard_menu dashboard requires symfony/security-bundle (security.authorization_checker), or set nowo_dashboard_menu.security.allow_unauthenticated: true (dev/demo only — never in production).');
        }

        if ($allowUnauthenticated) {
            return;
        }

        /** @var list<string> $accessRoles */
        $accessRoles      = $container->getParameter(Configuration::ALIAS . '.security.access_roles');
        $hasCustomChecker = (bool) $container->getParameter(Configuration::ALIAS . '.security.custom_access_checker');
        if ($accessRoles === [] && !$hasCustomChecker) {
            return;
        }

        if ($container->hasDefinition(DashboardAccessSubscriber::class)) {
            return;
        }

        $container->register(DashboardAccessSubscriber::class, DashboardAccessSubscriber::class)
            ->setArgument('$accessChecker', new Reference(DashboardMenuAccessCheckerInterface::class))
            ->addTag('kernel.event_subscriber');
    }
}
