<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;
use const SORT_STRING;

/**
 * Menu container (e.g. sidebar, topbar). Items are attached to a menu and form a tree.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[ORM\Table(name: 'dashboard_menu', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_menu_code_context', columns: ['code', 'attributes_key'])])]
#[ORM\HasLifecycleCallbacks]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore property.unusedType (Doctrine assigns id after persist) */
    private ?int $id = null;

    /**
     * Code to reference this menu (e.g. "sidebar", "topbar"). Uniqueness is (code + context).
     */
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $code = '';

    /**
     * Canonical key for context (for unique constraint). Empty string when context is null or empty.
     */
    #[ORM\Column(name: 'attributes_key', type: Types::STRING, length: 512, options: ['default' => ''])]
    private string $contextKey = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $name = null;

    /**
     * Optional icon identifier for this menu (e.g. "heroicons:bars-3", "bootstrap:list").
     */
    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $icon = null;

    /**
     * CSS class for the root <ul> (e.g. "nav flex-column"). Overrides config when set.
     */
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $classMenu = null;

    /**
     * CSS class for each <li> (e.g. "nav-item"). Overrides config when set.
     */
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $classItem = null;

    /**
     * CSS class for each <a> (e.g. "nav-link"). Overrides config when set.
     */
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $classLink = null;

    /**
     * CSS class for nested <ul> (e.g. "nav flex-column ms-2"). Overrides config when set.
     */
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $classChildren = null;

    /**
     * CSS class for section label span (itemType "section") (e.g. "navigation-header"). Overrides config when set.
     */
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $classSectionLabel = null;

    /**
     * Class added to the <a> when its route matches the current request (e.g. "active"). Overrides config when set.
     */
    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $classCurrent = null;

    /**
     * Class added to the <li> when the current route is in that branch (e.g. "active-branch"). Overrides config when set.
     */
    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $classBranchExpanded = null;

    /**
     * Class added to the <li> when the item has children. Overrides config when set.
     */
    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $classHasChildren = null;

    /**
     * Class added to the <li> when its children block is initially expanded. Overrides config when set.
     */
    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $classExpanded = null;

    /**
     * Class added to the <li> when it has children and its children block is initially collapsed. Overrides config when set.
     */
    #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
    private ?string $classCollapsed = null;

    /**
     * Service id of MenuPermissionCheckerInterface to filter visible items (null = allow all).
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $permissionChecker = null;

    /**
     * Max depth to render (null = unlimited). 1 = root only, 2 = root + one level, etc.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $depthLimit = null;

    /**
     * When true, the menu is wrapped in a collapsible block (toggle + content).
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $collapsible = null;

    /**
     * When collapsible is true: true = open by default, false = collapsed by default.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $collapsibleExpanded = null;

    /**
     * When true, each item with children is rendered with a collapse toggle.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $nestedCollapsible = null;

    /**
     * Root-level items (children of null). Fetched via repository; not persisted as inverse side.
     *
     * @var Collection<int, MenuItem>
     */
    #[ORM\OneToMany(targetEntity: MenuItem::class, mappedBy: 'menu', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    /**
     * Optional JSON key-value pairs (context) to identify this menu variant. Same code can have multiple menus with different context;
     * when resolving, the first match in the ordered list of context sets is used. Empty/null = fallback for that code.
     *
     * @var array<string, bool|int|string>|null
     */
    #[ORM\Column(name: 'attributes', type: Types::JSON, nullable: true)]
    private ?array $context = null;

    /**
     * When true, this menu is a "base" menu: its code cannot be changed after creation.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $base = false;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

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

    public function getClassMenu(): ?string
    {
        return $this->classMenu;
    }

    public function setClassMenu(?string $classMenu): self
    {
        $this->classMenu = $classMenu;

        return $this;
    }

    public function getClassItem(): ?string
    {
        return $this->classItem;
    }

    public function setClassItem(?string $classItem): self
    {
        $this->classItem = $classItem;

        return $this;
    }

    public function getClassLink(): ?string
    {
        return $this->classLink;
    }

    public function setClassLink(?string $classLink): self
    {
        $this->classLink = $classLink;

        return $this;
    }

    public function getClassChildren(): ?string
    {
        return $this->classChildren;
    }

    public function setClassChildren(?string $classChildren): self
    {
        $this->classChildren = $classChildren;

        return $this;
    }

    public function getClassSectionLabel(): ?string
    {
        return $this->classSectionLabel;
    }

    public function setClassSectionLabel(?string $classSectionLabel): self
    {
        $this->classSectionLabel = $classSectionLabel;

        return $this;
    }

    public function getClassCurrent(): ?string
    {
        return $this->classCurrent;
    }

    public function setClassCurrent(?string $classCurrent): self
    {
        $this->classCurrent = $classCurrent;

        return $this;
    }

    public function getClassBranchExpanded(): ?string
    {
        return $this->classBranchExpanded;
    }

    public function setClassBranchExpanded(?string $classBranchExpanded): self
    {
        $this->classBranchExpanded = $classBranchExpanded;

        return $this;
    }

    public function getClassHasChildren(): ?string
    {
        return $this->classHasChildren;
    }

    public function setClassHasChildren(?string $classHasChildren): self
    {
        $this->classHasChildren = $classHasChildren;

        return $this;
    }

    public function getClassExpanded(): ?string
    {
        return $this->classExpanded;
    }

    public function setClassExpanded(?string $classExpanded): self
    {
        $this->classExpanded = $classExpanded;

        return $this;
    }

    public function getClassCollapsed(): ?string
    {
        return $this->classCollapsed;
    }

    public function setClassCollapsed(?string $classCollapsed): self
    {
        $this->classCollapsed = $classCollapsed;

        return $this;
    }

    public function getPermissionChecker(): ?string
    {
        return $this->permissionChecker;
    }

    public function setPermissionChecker(?string $permissionChecker): self
    {
        $this->permissionChecker = $permissionChecker;

        return $this;
    }

    public function getDepthLimit(): ?int
    {
        return $this->depthLimit;
    }

    public function setDepthLimit(?int $depthLimit): self
    {
        $this->depthLimit = $depthLimit;

        return $this;
    }

    public function getCollapsible(): ?bool
    {
        return $this->collapsible;
    }

    public function setCollapsible(?bool $collapsible): self
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    public function getCollapsibleExpanded(): ?bool
    {
        return $this->collapsibleExpanded;
    }

    public function setCollapsibleExpanded(?bool $collapsibleExpanded): self
    {
        $this->collapsibleExpanded = $collapsibleExpanded;

        return $this;
    }

    public function getNestedCollapsible(): ?bool
    {
        return $this->nestedCollapsible;
    }

    public function setNestedCollapsible(?bool $nestedCollapsible): self
    {
        $this->nestedCollapsible = $nestedCollapsible;

        return $this;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(MenuItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setMenu($this);
        }

        return $this;
    }

    public function removeItem(MenuItem $item): self
    {
        if ($this->items->removeElement($item) && $item->getMenu() === $this) {
            $item->setMenu(null);
        }

        return $this;
    }

    /**
     * @return array<string, bool|int|string>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * @param array<string, bool|int|string>|null $context
     */
    public function setContext(?array $context): self
    {
        $this->context    = $context;
        $this->contextKey = self::canonicalContextKey($context);

        return $this;
    }

    public function getContextKey(): string
    {
        return $this->contextKey;
    }

    /**
     * Canonical string for context (for uniqueness and lookups). Empty when null or empty array.
     *
     * @param array<string, bool|int|string>|null $context
     */
    public static function canonicalContextKey(?array $context): string
    {
        if ($context === null || $context === []) {
            return '';
        }
        ksort($context, SORT_STRING);

        return json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function isBase(): bool
    {
        return $this->base;
    }

    public function setBase(bool $base): self
    {
        $this->base = $base;

        return $this;
    }

    /**
     * Sync contextKey from context when loading existing rows that may not have the key backfilled yet.
     */
    #[ORM\PostLoad]
    public function ensureContextKey(): void
    {
        if ($this->contextKey === '' && $this->context !== null && $this->context !== []) {
            $this->contextKey = self::canonicalContextKey($this->context);
        }
    }
}
