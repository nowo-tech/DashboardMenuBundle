<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection;

use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\DefaultMenuCodeResolver;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function dirname;
use function is_array;

/**
 * Loads bundle configuration and services.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DashboardMenuExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $fullConfig = [
            'project' => $config['project'] ?? null,
        ];

        $container->setParameter(Configuration::ALIAS . '.config', $fullConfig);
        $locales       = $config['locales'] ?? [];
        $locales       = is_array($locales) ? $locales : [];
        $defaultLocale = $config['default_locale'] ?? null;
        $container->setParameter(Configuration::ALIAS . '.locales', $locales);
        $container->setParameter(Configuration::ALIAS . '.default_locale', $defaultLocale);
        $container->setParameter(Configuration::ALIAS . '.default_locale_resolved', $defaultLocale ?? ($locales[0] ?? 'en'));

        $container->register(MenuLocaleResolver::class, MenuLocaleResolver::class)
            ->setArguments([
                '%' . Configuration::ALIAS . '.locales%',
                '%' . Configuration::ALIAS . '.default_locale%',
            ])
            ->setPublic(false);

        $container->setParameter(Configuration::ALIAS . '.api.enabled', $config['api']['enabled']);
        $container->setParameter(Configuration::ALIAS . '.api.path_prefix', $config['api']['path_prefix']);
        $container->setParameter(Configuration::ALIAS . '.dashboard.enabled', $config['dashboard']['enabled'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.dashboard.path_prefix', $config['dashboard']['path_prefix'] ?? '/admin/menus');
        $container->setParameter(Configuration::ALIAS . '.dashboard.route_name_exclude_patterns', $config['dashboard']['route_name_exclude_patterns'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.dashboard.pagination.enabled', $config['dashboard']['pagination']['enabled'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.dashboard.pagination.per_page', $config['dashboard']['pagination']['per_page'] ?? 20);
        $container->setParameter(Configuration::ALIAS . '.dashboard.css_class_options', $config['dashboard']['css_class_options'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.dashboard.modals', $config['dashboard']['modals'] ?? [
            'menu_form' => 'normal',
            'copy'      => 'normal',
            'item_form' => 'lg',
            'delete'    => 'normal',
        ]);
        $container->setParameter(Configuration::ALIAS . '.table_prefix', '');

        $container->register(MenuConfigResolver::class, MenuConfigResolver::class)
            ->setArguments([
                '%' . Configuration::ALIAS . '.config%',
                new Reference(MenuRepository::class),
            ])
            ->setPublic(false);

        $container->register(DefaultMenuCodeResolver::class, DefaultMenuCodeResolver::class)
            ->setPublic(false);
        $container->setAlias(MenuCodeResolverInterface::class, DefaultMenuCodeResolver::class)
            ->setPublic(false);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundleDir = dirname(__DIR__, 2);
        $viewsPath = $bundleDir . '/src/Resources/views';

        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    $viewsPath => 'NowoDashboardMenuBundle',
                ],
            ]);
        }

        $translationsPath = $bundleDir . '/src/Resources/translations';
        if ($container->hasExtension('framework') && is_dir($translationsPath)) {
            $container->prependExtensionConfig('framework', [
                'translator' => [
                    'paths' => [$translationsPath],
                ],
            ]);
        }
    }
}
