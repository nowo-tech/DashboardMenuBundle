<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Model;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;

/**
 * Immutable render configuration of a menu (2.18, architecture bar rule 1): what {@see MenuTreeLoader::loadMenu()}
 * returns as an entity, as scalars — for hosts that render or serialise the menu without the bundle's entities.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuView
{
    /**
     * @param array<string, bool|int|string>|null $context
     * @param array<string, string|null> $classes CSS classes keyed like the Menu setters without the `class` prefix
     *                                            (menu, item, link, children, sectionChildren, sectionChildItem,
     *                                            sectionChildLink, sectionLabel, section, divider, current,
     *                                            branchExpanded, hasChildren, expanded, collapsed)
     */
    public function __construct(
        public ?int $id,
        public string $code,
        public ?string $name,
        public ?string $icon,
        public ?array $context,
        public ?string $ulId,
        public array $classes,
        public ?string $permissionChecker,
        public ?int $depthLimit,
        public ?bool $collapsible,
        public ?bool $collapsibleExpanded,
        public ?bool $nestedCollapsible,
        public ?bool $nestedCollapsibleSections,
    ) {
    }

    public static function fromEntity(Menu $menu): self
    {
        return new self(
            $menu->getId(),
            $menu->getCode(),
            $menu->getName(),
            $menu->getIcon(),
            $menu->getContext(),
            $menu->getUlId(),
            [
                'menu'             => $menu->getClassMenu(),
                'item'             => $menu->getClassItem(),
                'link'             => $menu->getClassLink(),
                'children'         => $menu->getClassChildren(),
                'sectionChildren'  => $menu->getClassSectionChildren(),
                'sectionChildItem' => $menu->getClassSectionChildItem(),
                'sectionChildLink' => $menu->getClassSectionChildLink(),
                'sectionLabel'     => $menu->getClassSectionLabel(),
                'section'          => $menu->getClassSection(),
                'divider'          => $menu->getClassDivider(),
                'current'          => $menu->getClassCurrent(),
                'branchExpanded'   => $menu->getClassBranchExpanded(),
                'hasChildren'      => $menu->getClassHasChildren(),
                'expanded'         => $menu->getClassExpanded(),
                'collapsed'        => $menu->getClassCollapsed(),
            ],
            $menu->getPermissionChecker(),
            $menu->getDepthLimit(),
            $menu->getCollapsible(),
            $menu->getCollapsibleExpanded(),
            $menu->getNestedCollapsible(),
            $menu->getNestedCollapsibleSections(),
        );
    }
}
