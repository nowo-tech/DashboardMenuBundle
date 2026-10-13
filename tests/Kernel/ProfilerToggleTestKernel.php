<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

use function dirname;
use function sys_get_temp_dir;

/**
 * Real kernel (FrameworkBundle + DoctrineBundle + this bundle) whose profiler can be toggled,
 * used to check that the container compiles with and without {@code framework.profiler}.
 */
final class ProfilerToggleTestKernel extends BaseKernel
{
    public function __construct(string $environment, private readonly bool $profilerEnabled)
    {
        parent::__construct($environment, true);
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new NowoDashboardMenuBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo_dashboard_menu_profiler_kernel/' . $this->environment . '_' . ($this->profilerEnabled ? 'on' : 'off') . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo_dashboard_menu_profiler_kernel/log';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $configDir = $this->getProjectDir() . '/tests/config';
        $loader->load($configDir . '/framework.yaml');
        $loader->load($configDir . '/doctrine.yaml');
        $profilerEnabled = $this->profilerEnabled;
        $loader->load(static function (ContainerBuilder $container) use ($profilerEnabled): void {
            $container->loadFromExtension('framework', [
                'profiler' => ['enabled' => $profilerEnabled, 'collect' => false],
            ]);
        });
    }

    /**
     * Keep the Twig extension (unused without TwigBundle) so tests can inspect its injected collector.
     */
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition(MenuExtension::class)->setPublic(true);
            }
        });
    }
}
