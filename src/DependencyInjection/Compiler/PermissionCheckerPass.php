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
 * Merges with permission_checker_choices from bundle config (YAML adds/overrides labels).
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
        if ($container->hasParameter(self::PARAM_CHOICES)) {
            $override = $container->getParameter(self::PARAM_CHOICES);
            if (is_array($override)) {
                foreach ($override as $id => $label) {
                    if (is_string($label)) {
                        $choices[$id] = $label;
                    }
                }
            }
        }
        ksort($choices, SORT_NATURAL);
        $container->setParameter(self::PARAM_CHOICES, $choices);
    }
}
