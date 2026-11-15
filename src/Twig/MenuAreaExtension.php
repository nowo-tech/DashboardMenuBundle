<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Twig;

use Nowo\DashboardMenuBundle\Model\MenuArea;
use Nowo\DashboardMenuBundle\Model\MenuHubCard;
use Nowo\DashboardMenuBundle\Model\MenuHubSection;
use Nowo\DashboardMenuBundle\Service\MenuAreaResolver;
use Nowo\DashboardMenuBundle\Service\MenuHubBadgeProviderInterface;
use Nowo\DashboardMenuBundle\Service\MenuHubBuilder;
use Nowo\DashboardMenuBundle\Service\MenuRequestContextFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Areas and hub pages in Twig (2.22):
 * - `dashboard_menu_area()`: the area of the current (main) request, or null ({@see MenuAreaResolver});
 * - `dashboard_menu_areas()`: the areas the user may see (`dashboard_menu_areas(false)`: every area);
 * - `dashboard_menu_area_granted(area)`: whether the user may see an area (name or {@see MenuArea});
 * - `dashboard_menu_hub(menuCode, permissionContext?, contextSets?)`: the hub sections built from that menu's tree,
 *   the same tree `dashboard_menu_tree()` returns ({@see MenuHubBuilder});
 * - `dashboard_menu_hub_badge(card)`: the badge count of a card (0 = no badge; {@see MenuHubBadgeProviderInterface}).
 *
 * Nothing is kept between calls (FrankenPHP worker mode): every call reads the request stack.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuAreaExtension extends AbstractExtension
{
    /**
     * @param iterable<MenuHubBadgeProviderInterface> $badgeProviders
     */
    public function __construct(
        private readonly MenuAreaResolver $areas,
        private readonly MenuHubBuilder $hubBuilder,
        private readonly MenuExtension $menuExtension,
        private readonly RequestStack $requestStack,
        private readonly iterable $badgeProviders = [],
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('dashboard_menu_area', $this->currentArea(...)),
            new TwigFunction('dashboard_menu_areas', $this->areaList(...)),
            new TwigFunction('dashboard_menu_area_granted', $this->areas->isGranted(...)),
            new TwigFunction('dashboard_menu_hub', $this->hub(...)),
            new TwigFunction('dashboard_menu_hub_badge', $this->badge(...)),
        ];
    }

    /** The area of the main request (the URL the user opened), or the default area outside a request. */
    public function currentArea(): ?MenuArea
    {
        $request = $this->requestStack->getMainRequest() ?? $this->requestStack->getCurrentRequest();

        return $request instanceof Request
            ? $this->areas->resolveFor(MenuRequestContextFactory::fromRequest($request))
            : $this->areas->defaultArea();
    }

    /**
     * @return list<MenuArea>
     */
    public function areaList(bool $grantedOnly = true): array
    {
        return $grantedOnly ? $this->areas->granted() : $this->areas->all();
    }

    /**
     * @param list<array<string, bool|int|string>|null>|null $contextSets
     *
     * @return list<MenuHubSection>
     */
    public function hub(string $menuCode, mixed $permissionContext = null, ?array $contextSets = null): array
    {
        return $this->hubBuilder->build($this->menuExtension->getMenuTree($menuCode, $permissionContext, $contextSets));
    }

    public function badge(MenuHubCard $card): int
    {
        if ($card->badge === null) {
            return 0;
        }
        foreach ($this->badgeProviders as $provider) {
            $count = $provider->badgeCount($card->badge, $card);
            if ($count !== null) {
                return $count;
            }
        }

        return 0;
    }
}
