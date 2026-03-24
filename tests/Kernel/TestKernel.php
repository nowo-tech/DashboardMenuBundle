<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

use function dirname;

final class TestKernel extends BaseKernel
{
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

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $configDir = $this->getProjectDir() . '/tests/config';
        $loader->load($configDir . '/framework.yaml');
        $loader->load($configDir . '/doctrine.yaml');
    }
}
