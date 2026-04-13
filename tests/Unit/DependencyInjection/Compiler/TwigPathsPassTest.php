<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function count;

final class TwigPathsPassTest extends TestCase
{
    private ?string $tempProjectDir = null;

    protected function tearDown(): void
    {
        if ($this->tempProjectDir !== null) {
            $this->removeTree($this->tempProjectDir);
            $this->tempProjectDir = null;
        }
    }

    public function testProcessAddsTwigPathToNativeFilesystemLoader(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/nonexistent/project/nowo_dashboard_menu_no_overrides');
        $loaderDef = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loaderDef);

        (new TwigPathsPass())->process($container);

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
        self::assertSame(0, $this->countMethodCalls($calls, 'prependPath'));
    }

    public function testProcessPrependsOverrideDirectoryWhenItExists(): void
    {
        $this->tempProjectDir = sys_get_temp_dir() . '/nowo_dm_twig_' . bin2hex(random_bytes(4));
        mkdir($this->tempProjectDir . '/templates/bundles/NowoDashboardMenuBundle', 0777, true);

        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->tempProjectDir);
        $loaderDef = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loaderDef);

        (new TwigPathsPass())->process($container);

        $calls = $loaderDef->getMethodCalls();
        self::assertGreaterThanOrEqual(2, count($calls));
        self::assertSame('prependPath', $calls[0][0]);
        self::assertSame($this->tempProjectDir . '/templates/bundles/NowoDashboardMenuBundle', $calls[0][1][0]);
        self::assertSame('NowoDashboardMenuBundle', $calls[0][1][1]);
        self::assertSame('addPath', $calls[1][0]);
    }

    public function testProcessUsesTwigLoaderNativeWhenAliasExists(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/nonexistent/project/nowo_dashboard_menu_no_overrides');
        $loaderDef = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loaderDef);
        $container->setAlias('twig.loader.native', 'twig.loader.native_filesystem');

        (new TwigPathsPass())->process($container);

        $calls = $loaderDef->getMethodCalls();
        self::assertNotEmpty($calls);
        $addPathCalls = array_filter($calls, static fn (array $c): bool => $c[0] === 'addPath' && ($c[1][1] ?? '') === 'NowoDashboardMenuBundle');
        self::assertCount(1, $addPathCalls);
    }

    public function testProcessResolvesNestedTwigLoaderNativeAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/nonexistent/project/nowo_dashboard_menu_no_overrides');
        $loaderDef = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loaderDef);
        $container->setAlias('twig.loader.inner', 'twig.loader.native_filesystem');
        $container->setAlias('twig.loader.native', 'twig.loader.inner');

        (new TwigPathsPass())->process($container);

        $calls = $loaderDef->getMethodCalls();
        self::assertNotEmpty($calls);
        self::assertSame('addPath', $calls[count($calls) - 1][0]);
    }

    public function testProcessAddsTwigPathToTwigLoaderNativeWhenNativeDefinitionExists(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/nonexistent/project/nowo_dashboard_menu_no_overrides');
        $loaderDef = new Definition();
        $container->setDefinition('twig.loader.native', $loaderDef);

        (new TwigPathsPass())->process($container);

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
        $container->setParameter('kernel.project_dir', '/tmp');

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.native_filesystem'));
        self::assertFalse($container->hasDefinition('twig.loader.native'));
    }

    /**
     * @param list<array{0: string, 1: array<int, mixed>}> $calls
     */
    private function countMethodCalls(array $calls, string $method): int
    {
        $n = 0;
        foreach ($calls as [$m]) {
            if ($m === $method) {
                ++$n;
            }
        }

        return $n;
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
