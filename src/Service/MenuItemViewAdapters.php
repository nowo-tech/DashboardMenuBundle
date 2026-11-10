<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Symfony\Component\HttpFoundation\Request;

/**
 * Calls the entity ports or their {@see MenuItemView} variants on a host service.
 * The entity port wins when a service implements both.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuItemViewAdapters
{
    public static function canView(MenuPermissionCheckerInterface|MenuItemViewPermissionCheckerInterface $checker, MenuItem $item, mixed $context): bool
    {
        if ($checker instanceof MenuPermissionCheckerInterface) {
            return $checker->canView($item, $context);
        }

        return $checker->canViewItem(MenuItemView::fromEntity($item), $context);
    }

    /**
     * @return list<array{label: string, href: string, position: int, icon?: string|null, targetBlank?: bool}>|string
     */
    public static function resolveHref(MenuLinkResolverInterface|MenuItemViewLinkResolverInterface $resolver, MenuItem $item, ?Request $request, mixed $context): string|array
    {
        if ($resolver instanceof MenuLinkResolverInterface) {
            return $resolver->resolveHref($item, $request, $context);
        }

        return $resolver->resolveItemHref(MenuItemView::fromEntity($item), $request, $context);
    }

    public static function isCurrent(MenuCurrentMatcherInterface|MenuItemViewCurrentMatcherInterface $matcher, MenuItem $item, Request $request, string $href): bool
    {
        if ($matcher instanceof MenuCurrentMatcherInterface) {
            return $matcher->isCurrent($item, $request, $href);
        }

        return $matcher->isCurrentItem(MenuItemView::fromEntity($item), $request, $href);
    }
}
