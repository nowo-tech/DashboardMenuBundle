<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function dirname;

final class RegisterTwigNamespacePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig.loader.native_filesystem')) {
            return;
        }

        $viewsPath = dirname(__DIR__, 2) . '/Resources/views';

        // Ensure the namespace exists, but keep application overrides first.
        // Twig's FilesystemLoader resolves templates by checking earlier paths first.
        $container->getDefinition('twig.loader.native_filesystem')
            ->addMethodCall('addPath', [$viewsPath, 'NowoDashboardMenuBundle']);
    }
}
