<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\EntityManager;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

use function dirname;
use function getmypid;
use function hash;
use function serialize;
use function sys_get_temp_dir;

/**
 * Host kernel with a configurable Doctrine setup (2.18, `doctrine.entity_manager`). One cache directory per
 * configuration and PHPUnit process.
 */
final class EntityManagerTestKernel extends Kernel
{
    use MicroKernelTrait;

    private readonly string $cacheKey;

    /**
     * @param array<string, mixed> $doctrine
     * @param array<string, mixed> $menuConfig
     */
    public function __construct(
        private readonly array $doctrine,
        private readonly array $menuConfig = [],
    ) {
        $this->cacheKey = hash('xxh128', serialize([$doctrine, $menuConfig]));
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new NowoUiKitBundle();
        yield new NowoFormKitBundle();
        yield new NowoDashboardMenuBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-dashboard-menu-em/' . getmypid() . '/' . $this->cacheKey . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-dashboard-menu-em/' . getmypid() . '/' . $this->cacheKey . '/log';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret'               => 'test',
                'test'                 => true,
                'http_method_override' => false,
                'router'               => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
            ]);
            $container->loadFromExtension('doctrine', $this->doctrine);
            $container->loadFromExtension('nowo_dashboard_menu', $this->menuConfig + ['locales' => ['en']]);
        });
    }
}
