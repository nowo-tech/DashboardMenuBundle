<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Twig;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\CurrentRouteTreeDecorator;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension: dashboard_menu_tree(menuCode, permissionContext?, contextSets?), dashboard_menu_href(item), dashboard_menu_config(menuCode, contextSets?).
 * contextSets: ordered list of JSON key-value objects to try when resolving menu (code + context); use null/[] for no context.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly MenuTreeLoader $menuTreeLoader,
        private readonly MenuUrlResolver $urlResolver,
        private readonly MenuConfigResolver $configResolver,
        private readonly MenuCodeResolverInterface $menuCodeResolver,
        private readonly RequestStack $requestStack,
        private readonly CurrentRouteTreeDecorator $currentRouteTreeDecorator,
        private readonly MenuLocaleResolver $localeResolver,
    ) {
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
     * Returns render config for the menu (classes, depth_limit, icons). When multiple menus share the same code,
     * pass contextSets (ordered list of context objects) to resolve the first match; null/[] = no context.
     *
     * @param list<array<string, bool|int|string>|null>|null $contextSets
     *
     * @return array{classes: array<string, string>, depth_limit: int|null, icons: array{enabled: bool, use_ux_icons: bool, default: string|null}, collapsible: bool, collapsible_expanded: bool, nested_collapsible: bool, menu_name: string|null}
     */
    public function getMenuConfig(string $menuCode, ?array $contextSets = null): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $code    = $request instanceof \Symfony\Component\HttpFoundation\Request ? $this->menuCodeResolver->resolveMenuCode($request, $menuCode) : $menuCode;
        $config  = $this->configResolver->getConfig($code, $contextSets);

        return [
            'classes'              => $config['classes'],
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
        if ($context === null && $request instanceof \Symfony\Component\HttpFoundation\Request) {
            $context = $request;
        }
        if (!$request instanceof \Symfony\Component\HttpFoundation\Request) {
            return $this->menuTreeLoader->loadTree($menuCode, 'en', $context, $contextSets);
        }
        $resolvedCode = $this->menuCodeResolver->resolveMenuCode($request, $menuCode);
        $locale       = $this->localeResolver->resolveLocale($request->getLocale());
        $tree         = $this->menuTreeLoader->loadTree($resolvedCode, $locale, $context, $contextSets);

        return $this->currentRouteTreeDecorator->decorate($tree, $request);
    }
}
