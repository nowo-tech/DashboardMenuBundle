<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Twig;

use Doctrine\DBAL\Connection;
use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\CurrentRouteTreeDecorator;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension: dashboard_menu_tree(menuCode, permissionContext?, contextSets?), dashboard_menu_href(item), dashboard_menu_config(menuCode, contextSets?).
 * contextSets: ordered list of JSON key-value objects to try when resolving menu (code + context); use null/[] for no context.
 * Global nowo_dashboard_layout_template: layout template that dashboard views extend (from nowo_dashboard_menu.dashboard.layout_template).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly MenuTreeLoader $menuTreeLoader,
        private readonly MenuUrlResolver $urlResolver,
        private readonly MenuConfigResolver $configResolver,
        private readonly MenuCodeResolverInterface $menuCodeResolver,
        private readonly RequestStack $requestStack,
        private readonly CurrentRouteTreeDecorator $currentRouteTreeDecorator,
        private readonly MenuLocaleResolver $localeResolver,
        private readonly MenuIconNameResolver $menuIconNameResolver,
        private readonly string $dashboardLayoutTemplate,
        private readonly bool $uxAutocompleteAvailable = false,
        private readonly ?DashboardMenuDataCollector $dataCollector = null,
        private readonly ?MenuQueryCounter $menuQueryCounter = null,
        private readonly ?Connection $connection = null,
        private readonly bool $itemSpanActive = false,
        private readonly string $itemSpanClass = 'd-flex align-items-center flex-nowrap',
        private readonly string $iconSize = '1em',
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'nowo_dashboard_layout_template'           => $this->dashboardLayoutTemplate,
            'nowo_dashboard_ux_autocomplete_available' => $this->uxAutocompleteAvailable,
        ];
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('dashboard_menu_tree', $this->getMenuTree(...), ['is_safe' => ['html']]),
            new TwigFunction('dashboard_menu_href', $this->getHref(...)),
            new TwigFunction('dashboard_menu_config', $this->getMenuConfig(...)),
        ];
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('dashboard_menu_icon_name', $this->menuIconNameResolver->resolve(...)),
        ];
    }

    /**
     * Returns render config for the menu (classes, depth_limit, icons). When multiple menus share the same code,
     * pass contextSets (ordered list of context objects) to resolve the first match; null/[] = no context.
     *
     * @param list<array<string, bool|int|string>|null>|null $contextSets
     *
     * @return array{
     *     classes: array<string, string>,
     *     ul_id: string|null,
     *     item_span_active: bool,
     *     item_span_class: string,
     *     icon_size: string,
     *     depth_limit: int|null,
     *     icons: array{enabled: bool, use_ux_icons: bool, default: string|null},
     *     collapsible: bool,
     *     collapsible_expanded: bool,
     *     nested_collapsible: bool,
     *     menu_name: string|null
     * }
     */
    public function getMenuConfig(string $menuCode, ?array $contextSets = null): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $code    = $request instanceof Request ? $this->menuCodeResolver->resolveMenuCode($request, $menuCode) : $menuCode;
        $config  = $this->configResolver->getConfig($code, $contextSets);

        return [
            'classes'              => $config['classes'],
            'ul_id'                => $config['ul_id'],
            'item_span_active'     => $this->itemSpanActive,
            'item_span_class'      => $this->itemSpanClass,
            'icon_size'            => $this->iconSize,
            'depth_limit'          => $config['depth_limit'],
            'icons'                => $config['icons'],
            'collapsible'          => $config['collapsible'],
            'collapsible_expanded' => $config['collapsible_expanded'],
            'nested_collapsible'   => $config['nested_collapsible'],
            'menu_name'            => $config['menu_name'],
        ];
    }

    public function getHref(MenuItem $item, int $referenceType = 0): string
    {
        return $this->urlResolver->getHref($item, $referenceType);
    }

    /**
     * Returns the menu tree for the given hint (e.g. "sidebar"). When multiple menus share the same code,
     * pass contextSets (ordered list of context objects) to resolve the first match; null/[] = no context.
     *
     * @param list<array<string, bool|int|string>|null>|null $contextSets
     *
     * @return list<array{item: MenuItem, children: list<array>}>
     */
    public function getMenuTree(string $menuCode, mixed $permissionContext = null, ?array $contextSets = null): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $context = $permissionContext;
        if ($context === null && $request instanceof Request) {
            $context = $request;
        }
        if (!$request instanceof Request) {
            return $this->menuTreeLoader->loadTree($menuCode, 'en', $context, $contextSets);
        }
        $resolvedCode = $this->menuCodeResolver->resolveMenuCode($request, $menuCode);
        $locale       = $this->localeResolver->resolveLocale($request->getLocale());
        $queryCount   = null;
        if ($this->dataCollector instanceof DashboardMenuDataCollector && $this->menuQueryCounter instanceof MenuQueryCounter && $this->connection instanceof Connection) {
            $this->menuQueryCounter->wrapConnection($this->connection);
            $this->menuQueryCounter->startSegment();
        }
        $tree = $this->menuTreeLoader->loadTree($resolvedCode, $locale, $context, $contextSets);
        if ($this->menuQueryCounter instanceof MenuQueryCounter) {
            $queryCount = $this->menuQueryCounter->getSegmentCount();
        }

        if ($this->dataCollector instanceof DashboardMenuDataCollector) {
            $resolvedContext = null;
            if ($tree !== []) {
                $first = $tree[0]['item'];
                if ($first->getMenu() instanceof Menu) {
                    $resolvedContext = $first->getMenu()->getContext();
                }
            }
            $this->dataCollector->addMenuLoad($resolvedCode, $contextSets, $tree, $resolvedContext, $queryCount);
        }

        return $this->currentRouteTreeDecorator->decorate($tree, $request);
    }
}
