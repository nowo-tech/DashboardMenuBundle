<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\Service\AbstractRoutePrefixMenuCurrentMatcher;
use Nowo\DashboardMenuBundle\Service\MenuCurrentMatcherInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemContextCurrentMatcherInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemContextLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemViewCurrentMatcherInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemViewLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemViewPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Throwable;

use function array_filter;
use function array_values;
use function class_exists;
use function count;
use function implode;
use function is_a;
use function sprintf;
use function str_starts_with;

use const E_USER_DEPRECATED;

/**
 * Warns at container build when a host checker, link resolver or current matcher implements more than one variant of
 * its port. The bundle calls the oldest variant (entity, then `MenuItemView`, then value objects), so the newer method
 * of such a class is never called: a stricter rule written there would be dead code.
 *
 * The warning goes to the compiler log (`var/cache/<env>/<kernel>Compiler.log`) and is also emitted as a silenced
 * `E_USER_DEPRECATED` (the older variant is deprecated), which Symfony collects in debug into the container
 * deprecations log shown by `bin/console debug:container --deprecations` and the profiler.
 * Bundle classes are skipped (they implement both on purpose and answer the same), and so are subclasses of
 * {@see AbstractRoutePrefixMenuCurrentMatcher} that do not override its match methods.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MultiVariantPortsPass implements CompilerPassInterface
{
    /** @var array<string, list<class-string>> tag => variants, oldest first */
    private const PORTS = [
        'nowo_dashboard_menu.permission_checker' => [MenuPermissionCheckerInterface::class, MenuItemViewPermissionCheckerInterface::class, MenuItemContextPermissionCheckerInterface::class],
        'nowo_dashboard_menu.menu_link_resolver' => [MenuLinkResolverInterface::class, MenuItemViewLinkResolverInterface::class, MenuItemContextLinkResolverInterface::class],
        'nowo_dashboard_menu.current_matcher'    => [MenuCurrentMatcherInterface::class, MenuItemViewCurrentMatcherInterface::class, MenuItemContextCurrentMatcherInterface::class],
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::PORTS as $tag => $variants) {
            foreach ($container->findTaggedServiceIds($tag) as $id => $tags) {
                $warning = $this->warningFor($container->findDefinition($id)->getClass() ?? $id, $id, $variants);
                if ($warning !== null) {
                    $container->log($this, $warning);
                    @trigger_error($warning, E_USER_DEPRECATED);
                }
            }
        }
    }

    /**
     * @param list<class-string> $variants oldest first
     */
    public function warningFor(string $class, string $id, array $variants): ?string
    {
        try {
            if (!class_exists($class) || (str_starts_with($class, 'Nowo\\DashboardMenuBundle\\') && !str_starts_with($class, 'Nowo\\DashboardMenuBundle\\Tests\\'))) {
                return null;
            }
            $implemented = array_values(array_filter($variants, static fn (string $variant): bool => is_a($class, $variant, true)));
            if (count($implemented) < 2 || $this->onlyInheritsTheRoutePrefixMatcher($class)) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return sprintf('DashboardMenu: service "%s" (%s) implements several variants of one port (%s); the bundle calls only %s. Drop the deprecated interface so the newer method is used.', $id, $class, implode(', ', $implemented), $implemented[0]);
    }

    /**
     * @param class-string $class
     */
    private function onlyInheritsTheRoutePrefixMatcher(string $class): bool
    {
        if (!is_a($class, AbstractRoutePrefixMenuCurrentMatcher::class, true)) {
            return false;
        }
        $reflection = new ReflectionClass($class);

        return $reflection->getMethod('isCurrent')->getDeclaringClass()->getName() === AbstractRoutePrefixMenuCurrentMatcher::class
            && $reflection->getMethod('isCurrentItemFor')->getDeclaringClass()->getName() === AbstractRoutePrefixMenuCurrentMatcher::class;
    }
}
