<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_array;
use function is_string;

use const SORT_NATURAL;

/**
 * Collects all services tagged "nowo_dashboard_menu.permission_checker" and
 * builds the permission_checker_choices parameter (id => label) for the menu form.
 * Config is an ordered list of service IDs; dropdown label defaults to the service id
 * unless the tag sets label=.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PermissionCheckerPass implements CompilerPassInterface
{
    private const TAG           = 'nowo_dashboard_menu.permission_checker';
    private const PARAM_CHOICES = 'nowo_dashboard_menu.permission_checker_choices';

    public function process(ContainerBuilder $container): void
    {
        $choices = [];
        foreach ($container->findTaggedServiceIds(self::TAG, true) as $id => $tags) {
            $label = $id;
            foreach ($tags as $attrs) {
                if (isset($attrs['label']) && is_string($attrs['label'])) {
                    $label = $attrs['label'];
                    break;
                }
            }
            $choices[$id] = $label;
        }

        $config = $container->hasParameter(self::PARAM_CHOICES)
            ? $container->getParameter(self::PARAM_CHOICES)
            : [];
        if (!is_array($config)) {
            $config = [];
        }

        $order = self::extractOrderedServiceIds($config);

        if ($order !== []) {
            $ordered = [];
            foreach ($order as $id) {
                if (is_string($id) && isset($choices[$id])) {
                    $ordered[$id] = $choices[$id];
                }
            }
            foreach ($choices as $id => $label) {
                if (!isset($ordered[$id])) {
                    $ordered[$id] = $label;
                }
            }
            $choices = $ordered;
        } else {
            ksort($choices, SORT_NATURAL);
        }

        $container->setParameter(self::PARAM_CHOICES, $choices);
    }

    /**
     * @return list<string>
     */
    private static function extractOrderedServiceIds(mixed $config): array
    {
        if (!is_array($config)) {
            return [];
        }
        if (array_is_list($config)) {
            return array_values(array_filter($config, static fn ($id): bool => is_string($id)));
        }
        if (isset($config['order']) && is_array($config['order'])) {
            return array_values(array_filter($config['order'], static fn ($id): bool => is_string($id)));
        }

        return [];
    }
}
