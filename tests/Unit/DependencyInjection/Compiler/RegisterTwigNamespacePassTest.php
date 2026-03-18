<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\RegisterTwigNamespacePass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class RegisterTwigNamespacePassTest extends TestCase
{
    public function testProcessAddsTwigPathToNativeFilesystemLoader(): void
    {
        $container = new ContainerBuilder();
        $loaderDef = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loaderDef);

        $pass = new RegisterTwigNamespacePass();
        $pass->process($container);

        $calls = $loaderDef->getMethodCalls();
        self::assertNotEmpty($calls);

        $found = false;
        foreach ($calls as [$method, $args]) {
            if ($method !== 'addPath') {
                continue;
            }
            if (!isset($args[0], $args[1])) {
                continue;
            }
            if ($args[1] !== 'NowoDashboardMenuBundle') {
                continue;
            }
            self::assertStringEndsWith('/Resources/views', (string) $args[0]);
            $found = true;
            break;
        }

        self::assertTrue($found, 'Expected addPath call for NowoDashboardMenuBundle namespace.');
    }

    public function testProcessDoesNothingWhenTwigLoaderNotDefined(): void
    {
        $container = new ContainerBuilder();

        $pass = new RegisterTwigNamespacePass();
        $pass->process($container);

        self::assertTrue(true);
    }
}
