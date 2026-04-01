<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;

use function in_array;
use function is_string;
use function trim;

/**
 * Single menu entry: translatable label (label + optional translations JSON), link (route or external URL),
 * optional permission key, tree position (parent + position, no nested set).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
#[ORM\Table(name: 'dashboard_menu_item')]
#[ORM\Index(name: 'idx_menu_position', columns: ['menu_id', 'position'])]
#[ORM\Index(name: 'idx_parent_id', columns: ['parent_id'])]
class MenuItem implements TranslatableInterface
{
    public const LINK_TYPE_ROUTE    = 'route';
    public const LINK_TYPE_EXTERNAL = 'external';

    /** Item display: "link" (default), "section" (label only, no link), "divider" (hr). */
    public const ITEM_TYPE_LINK    = 'link';
    public const ITEM_TYPE_SECTION = 'section';
    public const ITEM_TYPE_DIVIDER = 'divider';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore property.unusedType (Doctrine assigns id after persist) */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Menu $menu = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?MenuItem $parent = null;

    /**
     * @var Collection<int, MenuItem>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $children;

    /**
     * Display order within the same parent.
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    /**
     * Label (default/fallback). For i18n use translations JSON; repository resolves by locale on load.
     * For dividers, null or empty is allowed (rendered as a horizontal rule); a name is optional but recommended for accessibility/listings.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $label = null;

    /**
     * Optional translations: {"en": "Home", "es": "Inicio"}. Resolved in repository by locale.
     *
     * @var array<string, string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $translations = null;

    /**
     * Link type: "route" (Symfony route) or "external" (full URL). Null when item type is "link" and has children (no destination).
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, options: ['default' => 'route'])]
    private ?string $linkType = self::LINK_TYPE_ROUTE;

    /**
     * Symfony route name when linkType = route.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $routeName = null;

    /**
     * Route parameters as JSON (e.g. {"tab": "configuration", "section": "**"}).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $routeParams = null;

    /**
     * Full URL when linkType = external.
     */
    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $externalUrl = null;

    /**
     * Legacy single permission key (kept for backward compatibility).
     */
    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $permissionKey = null;

    /**
     * Permission keys passed to the permission checker service for this item.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $permissionKeys = null;

    /**
     * Permission aggregation mode for multiple permission keys:
     * - true: all keys must pass (AND / unanimous)
     * - false: any key can pass (OR)
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isUnanimous = true;

    /**
     * Optional icon identifier (e.g. "heroicons:home", "bootstrap:house" for Symfony UX Icons).
     */
    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $icon = null;

    /**
     * Display type: "link" (default), "section" (label only), "divider" (horizontal rule). Nullable for form/import.
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, options: ['default' => 'link'])]
    private ?string $itemType = self::ITEM_TYPE_LINK;

    /**
     * When true, the link opens in a new tab (target="_blank" with rel="noopener noreferrer").
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $targetBlank = false;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): self
    {
        $this->menu = $menu;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? '';
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Label for a given locale (translations[locale] ?? label). Used by repository when loading with locale.
     */
    public function getLabelForLocale(string $locale): string
    {
        if ($this->translations !== null && isset($this->translations[$locale])) {
            return $this->translations[$locale];
        }

        return $this->label ?? '';
    }

    /**
     * @param array<string, string>|null $translations
     */
    public function setTranslations(?array $translations): self
    {
        $this->translations = $translations;

        return $this;
    }

    /**
     * @return array<string, string>|null
     */
    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    public function getLinkType(): ?string
    {
        return $this->linkType;
    }

    public function setLinkType(?string $linkType): self
    {
        $this->linkType = $linkType;

        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function setRouteName(?string $routeName): self
    {
        $this->routeName = $routeName;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRouteParams(): ?array
    {
        return $this->routeParams;
    }

    /**
     * @param array<string, mixed>|null $routeParams
     */
    public function setRouteParams(?array $routeParams): self
    {
        $this->routeParams = $routeParams;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = $externalUrl;

        return $this;
    }

    public function getPermissionKey(): ?string
    {
        return $this->permissionKey;
    }

    public function setPermissionKey(?string $permissionKey): self
    {
        $normalized           = $permissionKey !== null ? trim($permissionKey) : null;
        $this->permissionKey  = $normalized !== '' ? $normalized : null;
        $this->permissionKeys = $this->permissionKey !== null ? [$this->permissionKey] : null;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getPermissionKeys(): ?array
    {
        if ($this->permissionKeys !== null) {
            return $this->permissionKeys;
        }
        if ($this->permissionKey === null || $this->permissionKey === '') {
            return null;
        }

        return [$this->permissionKey];
    }

    /**
     * @param list<string>|null $permissionKeys
     */
    public function setPermissionKeys(?array $permissionKeys): self
    {
        if ($permissionKeys === null) {
            $this->permissionKeys = null;
            $this->permissionKey  = null;

            return $this;
        }

        $normalized = [];
        foreach ($permissionKeys as $key) {
            $value = trim($key);
            if ($value === '' || in_array($value, $normalized, true)) {
                continue;
            }
            $normalized[] = $value;
        }

        $this->permissionKeys = $normalized !== [] ? $normalized : null;
        $this->permissionKey  = $this->permissionKeys[0] ?? null;

        return $this;
    }

    public function isUnanimous(): bool
    {
        return $this->isUnanimous;
    }

    public function setIsUnanimous(bool $isUnanimous): self
    {
        $this->isUnanimous = $isUnanimous;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getItemType(): string
    {
        return $this->itemType ?? self::ITEM_TYPE_LINK;
    }

    public function setItemType(?string $itemType): self
    {
        $this->itemType = $itemType;

        return $this;
    }

    public function getTargetBlank(): bool
    {
        return $this->targetBlank;
    }

    public function setTargetBlank(bool $targetBlank): self
    {
        $this->targetBlank = $targetBlank;

        return $this;
    }

    /**
     * Dividers render as a horizontal rule at the root (no parent); icon is not used.
     * Trims optional label/translations and stores null when empty.
     */
    public function normalizeDividerState(): void
    {
        if ($this->getItemType() !== self::ITEM_TYPE_DIVIDER) {
            return;
        }

        $this->parent = null;
        $this->icon   = null;

        if ($this->label !== null) {
            $t           = trim($this->label);
            $this->label = $t === '' ? null : $t;
        }

        if ($this->translations !== null) {
            $filtered = [];
            foreach ($this->translations as $locale => $value) {
                if (!is_string($value)) {
                    continue;
                }
                $tv = trim($value);
                if ($tv !== '') {
                    $filtered[$locale] = $tv;
                }
            }
            $this->translations = $filtered === [] ? null : $filtered;
        }
    }
}
