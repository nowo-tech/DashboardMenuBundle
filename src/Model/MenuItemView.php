<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Model;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuItemViewPermissionCheckerInterface;

/**
 * Read-only, scalar view of a menu item for host adapters.
 *
 * Ports that take a {@see MenuItemView} (e.g. {@see MenuItemViewPermissionCheckerInterface})
 * let the host infrastructure layer work with plain values instead of the bundle's Doctrine entity.
 * Build one with {@see self::fromEntity()}; the bundle does this for you when it calls the view ports.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuItemView
{
    /**
     * @param array<string, string> $translations label per locale
     * @param array<string, mixed>|null $routeParams
     * @param list<string> $permissionKeys permission keys of the item (empty = none)
     */
    public function __construct(
        public ?int $id,
        public ?int $menuId,
        public ?string $menuCode,
        public ?int $parentId,
        public string $itemType,
        public string $label,
        public array $translations,
        public int $position,
        public ?string $linkType,
        public ?string $routeName,
        public ?array $routeParams,
        public ?string $externalUrl,
        public ?string $linkResolver,
        public ?string $runtimeHref,
        public array $permissionKeys,
        public bool $unanimous,
        public ?string $icon,
        public bool $targetBlank,
    ) {
    }

    /**
     * Copies the plain values of a {@see MenuItem}; no reference to the entity is kept.
     */
    public static function fromEntity(MenuItem $item): self
    {
        $menu = $item->getMenu();

        return new self(
            id: $item->getId(),
            menuId: $menu?->getId(),
            menuCode: $menu?->getCode(),
            parentId: $item->getParent()?->getId(),
            itemType: $item->getItemType(),
            label: $item->getLabel(),
            translations: $item->getTranslations() ?? [],
            position: $item->getPosition(),
            linkType: $item->getLinkType(),
            routeName: $item->getRouteName(),
            routeParams: $item->getRouteParams(),
            externalUrl: $item->getExternalUrl(),
            linkResolver: $item->getLinkResolver(),
            runtimeHref: $item->getRuntimeHref(),
            permissionKeys: $item->getPermissionKeys() ?? [],
            unanimous: $item->isUnanimous(),
            icon: $item->getIcon(),
            targetBlank: $item->getTargetBlank(),
        );
    }

    /**
     * Label for a locale (translations[locale] ?? label), like {@see MenuItem::getLabelForLocale()}.
     */
    public function labelForLocale(string $locale): string
    {
        return $this->translations[$locale] ?? $this->label;
    }
}
