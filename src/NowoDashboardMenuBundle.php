<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\PermissionCheckerPass;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Dashboard menu bundle: configurable menus with i18n, tree structure, permissions, Twig and JSON API.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoDashboardMenuBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PermissionCheckerPass());
    }

    /**
     * @return ExtensionInterface
     */
    public function getContainerExtension(): ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new DashboardMenuExtension();
        }

        /** @var ExtensionInterface $extension */
        $extension = $this->extension;

        return $extension;
    }
}
