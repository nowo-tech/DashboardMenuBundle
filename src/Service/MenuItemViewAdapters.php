<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Symfony\Component\HttpFoundation\Request;

/**
 * Calls the item ports on a host service: the deprecated entity ports, their {@see MenuItemView} variants (2.17) or the
 * request-free context variants (2.19).
 *
 * When a service implements several variants, the bundle calls the oldest one it implements (entity, then view, then
 * context), so a class that adds the new interface during its migration keeps its 2.18 behaviour until it drops the
 * old one.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuItemViewAdapters
{
    public static function canView(MenuPermissionCheckerInterface|MenuItemViewPermissionCheckerInterface|MenuItemContextPermissionCheckerInterface $checker, MenuItem $item, mixed $context): bool
    {
        if ($checker instanceof MenuPermissionCheckerInterface) {
            return $checker->canView($item, $context);
        }
        if ($checker instanceof MenuItemViewPermissionCheckerInterface) {
            return $checker->canViewItem(MenuItemView::fromEntity($item), $context);
        }

        return $checker->canViewItemFor(MenuItemView::fromEntity($item), MenuRequestContextFactory::permissionContext($context));
    }

    /**
     * @return list<array{label: string, href: string, position: int, icon?: string|null, targetBlank?: bool}>|string
     */
    public static function resolveHref(MenuLinkResolverInterface|MenuItemViewLinkResolverInterface|MenuItemContextLinkResolverInterface $resolver, MenuItem $item, ?Request $request, mixed $context): string|array
    {
        if ($resolver instanceof MenuLinkResolverInterface) {
            return $resolver->resolveHref($item, $request, $context);
        }
        if ($resolver instanceof MenuItemViewLinkResolverInterface) {
            return $resolver->resolveItemHref(MenuItemView::fromEntity($item), $request, $context);
        }

        return $resolver->resolveItemHrefFor(
            MenuItemView::fromEntity($item),
            $request instanceof Request ? MenuRequestContextFactory::fromRequest($request) : null,
            MenuRequestContextFactory::permissionContext($context),
        );
    }

    public static function isCurrent(MenuCurrentMatcherInterface|MenuItemViewCurrentMatcherInterface|MenuItemContextCurrentMatcherInterface $matcher, MenuItem $item, Request $request, string $href): bool
    {
        if ($matcher instanceof MenuCurrentMatcherInterface) {
            return $matcher->isCurrent($item, $request, $href);
        }
        if ($matcher instanceof MenuItemViewCurrentMatcherInterface) {
            return $matcher->isCurrentItem(MenuItemView::fromEntity($item), $request, $href);
        }

        return $matcher->isCurrentItemFor(MenuItemView::fromEntity($item), MenuRequestContextFactory::fromRequest($request), $href);
    }

    public static function resolveMenuCode(MenuCodeResolverInterface|MenuCodeContextResolverInterface $resolver, Request $request, string $hint): string
    {
        if ($resolver instanceof MenuCodeResolverInterface) {
            return $resolver->resolveMenuCode($request, $hint);
        }

        return $resolver->resolveMenuCodeFor(MenuRequestContextFactory::fromRequest($request), $hint);
    }
}
