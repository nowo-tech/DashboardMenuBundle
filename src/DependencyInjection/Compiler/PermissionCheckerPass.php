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
 * Config may provide an ordered list of IDs and/or label overrides (order + labels).
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

        $order  = $config['order'] ?? [];
        $labels = $config['labels'] ?? [];
        if (!is_array($order)) {
            $order = [];
        }
        if (!is_array($labels)) {
            $labels = [];
        }

        foreach ($labels as $id => $label) {
            if (is_string($id) && is_string($label)) {
                $choices[$id] = $label;
            }
        }

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
}
