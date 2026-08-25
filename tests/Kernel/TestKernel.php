<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Kernel;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

    /**
     * ORM 3.6.8 SchemaTool dispatches setSchema() which needs DBAL Schema::edit() (4.5-dev).
     * Keep the listener service id (event manager references it) but skip schema mutation.
     */
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ([
                    'doctrine.orm.listeners.doctrine_dbal_cache_adapter_schema_listener',
                    'doctrine.orm.listeners.doctrine_token_provider_schema_listener',
                    'doctrine.orm.listeners.pdo_session_handler_schema_listener',
                    'doctrine.orm.listeners.lock_store_schema_listener',
                ] as $id) {
                    if ($container->hasDefinition($id)) {
                        $container->getDefinition($id)
                            ->setClass(NoOpPostGenerateSchemaListener::class);
                    }
                }
            }
        });
    }
}
