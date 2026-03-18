<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;

/**
 * Resolves config for a given menu code: YAML (connection, table_prefix, cache) + Menu entity (DB) for name and CSS classes.
 * Rendering defaults (classes, icons, collapsible) are hardcoded; entity overrides classes when set.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuConfigResolver
{
    private const DEFAULT_CLASSES = [
        'menu'                  => 'dashboard-menu',
        'item'                  => '',
        'link'                  => '',
        'children'              => '',
        'section_label'         => 'menu-section-label',
        'class_current'         => 'active',
        'class_branch_expanded' => 'active-branch',
        'class_has_children'    => '',
        'class_expanded'        => '',
        'class_collapsed'       => '',
    ];

    /**
     * @param array{project: string|null} $config
     */
    public function __construct(
        private array $config,
        private MenuRepository $menuRepository,
        private string $connection = 'default',
        private string $tablePrefix = '',
    ) {
    }

    /**
     * Returns config for the given menu code: YAML values + Menu entity (DB) for name and classes.
     * When multiple menus share the same code (different context), pass $contextSets to try
     * combinations in order; the first matching menu is used. Use null/empty for "no context".
     * Pass $menu when already loaded (e.g. from cache) to avoid an extra DB query.
     *
     * @param list<array<string, bool|int|string>|null>|null $contextSets Ordered list of context objects to try; null = try [null, []] (no context first)
     * @param Menu|null $menu Optional pre-loaded menu entity to use instead of querying
     *
     * @return array{connection: string, table_prefix: string, menu_name: string|null, permission_checker: string|null, cache_pool: string|null, cache_ttl: int, classes: array<string, string>, depth_limit: int|null, icons: array{enabled: bool, use_ux_icons: bool, default: string|null}, collapsible: bool, collapsible_expanded: bool, nested_collapsible: bool, nested_collapsible_sections: bool, context: array<string, bool|int|string>}
     */
    public function getConfig(string $menuCode, ?array $contextSets = null, ?Menu $menu = null): array
    {
        $classes = self::DEFAULT_CLASSES;

        $sets   = $contextSets ?? [null, []];
        $entity = $menu ?? $this->menuRepository->findForCodeWithContextSets($menuCode, $sets);
        if ($entity instanceof Menu) {
            $classes = $this->mergeEntityClasses($classes, $entity);
        }

        return [
            'connection'         => $this->connection,
            'table_prefix'       => $this->tablePrefix,
            'menu_name'          => $entity?->getName(),
            'permission_checker' => $entity?->getPermissionChecker(),
            'cache_pool'         => null,
            'cache_ttl'          => 300,
            'classes'            => $classes,
            'depth_limit'        => $entity?->getDepthLimit(),
            'icons'              => [
                'enabled'      => true,
                'use_ux_icons' => false,
                'default'      => null,
            ],
            'collapsible'                 => $entity?->getCollapsible() ?? false,
            'collapsible_expanded'        => $entity?->getCollapsibleExpanded() ?? true,
            'nested_collapsible'          => $entity?->getNestedCollapsible() ?? false,
            'nested_collapsible_sections' => $entity?->getNestedCollapsibleSections() ?? true,
            'context'                     => $entity?->getContext() ?? [],
        ];
    }

    /**
     * @param array<string, string> $classes
     *
     * @return array<string, string>
     */
    private function mergeEntityClasses(array $classes, Menu $entity): array
    {
        if ($entity->getClassMenu() !== null && $entity->getClassMenu() !== '') {
            $classes['menu'] = $entity->getClassMenu();
        }
        if ($entity->getClassItem() !== null && $entity->getClassItem() !== '') {
            $classes['item'] = $entity->getClassItem();
        }
        if ($entity->getClassLink() !== null && $entity->getClassLink() !== '') {
            $classes['link'] = $entity->getClassLink();
        }
        if ($entity->getClassChildren() !== null && $entity->getClassChildren() !== '') {
            $classes['children'] = $entity->getClassChildren();
        }
        if ($entity->getClassSectionLabel() !== null && $entity->getClassSectionLabel() !== '') {
            $classes['section_label'] = $entity->getClassSectionLabel();
        }
        if ($entity->getClassCurrent() !== null && $entity->getClassCurrent() !== '') {
            $classes['class_current'] = $entity->getClassCurrent();
        }
        if ($entity->getClassBranchExpanded() !== null && $entity->getClassBranchExpanded() !== '') {
            $classes['class_branch_expanded'] = $entity->getClassBranchExpanded();
        }
        if ($entity->getClassHasChildren() !== null && $entity->getClassHasChildren() !== '') {
            $classes['class_has_children'] = $entity->getClassHasChildren();
        }
        if ($entity->getClassExpanded() !== null && $entity->getClassExpanded() !== '') {
            $classes['class_expanded'] = $entity->getClassExpanded();
        }
        if ($entity->getClassCollapsed() !== null && $entity->getClassCollapsed() !== '') {
            $classes['class_collapsed'] = $entity->getClassCollapsed();
        }

        return $classes;
    }

    public function getProject(): ?string
    {
        return $this->config['project'] ?? null;
    }

    /**
     * Returns all menu codes from the database (menus are registered only in DB).
     *
     * @return list<string>
     */
    public function getMenuCodes(): array
    {
        $menus = $this->menuRepository->findAllOrderedByCode();

        return array_map(static fn (Menu $m): string => $m->getCode(), $menus);
    }
}
