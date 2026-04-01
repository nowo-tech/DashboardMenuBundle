<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Util\ParentRelationCycleDetector;

use function array_keys;
use function array_merge;
use function array_shift;
use function assert;
use function count;
use function is_array;
use function is_int;
use function ksort;
use function usort;

/**
 * Load all items for a menu (one query). Order by parent then position.
 * Resolves label by locale from translations JSON after load.
 *
 * @extends ServiceEntityRepository<MenuItem>
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class MenuItemRepository extends ServiceEntityRepository
{
    /** Message for InvalidArgumentException when applyTreeLayout nests a section under a parent. */
    public const TREE_LAYOUT_SECTION_MUST_BE_ROOT = 'section_must_be_root';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    /**
     * Load all menu items for the given menu; labels resolved for the given locale (translations or fallback).
     *
     * @return list<MenuItem>
     */
    public function findAllForMenuOrderedByTree(Menu $menu, string $locale): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.menu = :menu')
            ->setParameter('menu', $menu)
            ->orderBy('i.parent', 'ASC')
            ->addOrderBy('i.position', 'ASC');

        $result = $qb->getQuery()->getResult();
        assert(is_array($result) && array_is_list($result));

        foreach ($result as $item) {
            $item->setLabel($item->getLabelForLocale($locale));
        }

        /* @var list<MenuItem> $result */
        return $result;
    }

    /**
     * Load all menu items for the given menu in tree order, without resolving label by locale.
     * Use for export so the stored label and translations are preserved.
     *
     * @return list<MenuItem>
     */
    public function findAllForMenuOrderedByTreeForExport(Menu $menu): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.menu = :menu')
            ->setParameter('menu', $menu)
            ->orderBy('i.parent', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->addOrderBy('i.id', 'ASC');

        $result = $qb->getQuery()->getResult();
        assert(is_array($result) && array_is_list($result));

        /* @var list<MenuItem> $result */
        return $result;
    }

    /**
     * Load all items for the given menus in one query, grouped by menu id.
     *
     * @param list<Menu> $menus
     *
     * @return array<int, list<MenuItem>> map menuId => ordered items
     */
    public function findAllForMenusOrderedByTreeForExport(array $menus): array
    {
        if ($menus === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('i')
            ->where('i.menu IN (:menus)')
            ->setParameter('menus', $menus)
            ->orderBy('i.menu', 'ASC')
            ->addOrderBy('i.parent', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->addOrderBy('i.id', 'ASC');

        $result = $qb->getQuery()->getResult();
        assert(is_array($result) && array_is_list($result));

        /** @var array<int, list<MenuItem>> $grouped */
        $grouped = [];
        foreach ($result as $item) {
            $menuId = $item->getMenu()?->getId();
            if ($menuId === null) {
                continue;
            }
            if (!isset($grouped[$menuId])) {
                $grouped[$menuId] = [];
            }
            $grouped[$menuId][] = $item;
        }

        return $grouped;
    }

    /**
     * Siblings of the given item (same parent), ordered by position.
     *
     * @return list<MenuItem>
     */
    public function findSiblingsByPosition(MenuItem $item): array
    {
        $parent = $item->getParent();
        $qb     = $this->createQueryBuilder('i')
            ->where('i.menu = :menu')
            ->setParameter('menu', $item->getMenu());
        if (!$parent instanceof MenuItem) {
            $qb->andWhere('i.parent IS NULL');
        } else {
            $qb->andWhere('i.parent = :parent')
                ->setParameter('parent', $parent);
        }
        $qb->orderBy('i.position', 'ASC');
        $qb->addOrderBy('i.id', 'ASC');
        $result = $qb->getQuery()->getResult();
        assert(is_array($result) && array_is_list($result));

        /* @var list<MenuItem> $result */
        return $result;
    }

    /**
     * Max position among siblings for the given menu and parent.
     *
     * @return int max position, or -1 when there are no siblings
     */
    public function findMaxPositionForParent(Menu $menu, ?MenuItem $parent): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('MAX(i.position)')
            ->where('i.menu = :menu')
            ->setParameter('menu', $menu);

        if (!$parent instanceof MenuItem) {
            $qb->andWhere('i.parent IS NULL');
        } else {
            $qb->andWhere('i.parent = :parent')
                ->setParameter('parent', $parent);
        }

        $max = $qb->getQuery()->getSingleScalarResult();
        if ($max === null) {
            return -1;
        }

        return (int) $max;
    }

    /**
     * Returns $rootItemId plus every descendant id in the same menu. Purely DB-driven (array hydration)
     * so it stays correct even when the editing entity has uninitialized associations.
     *
     * @return list<int>
     */
    public function findIdsInSubtreeStartingAt(Menu $menu, int $rootItemId): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.id AS itemId')
            ->addSelect('IDENTITY(i.parent) AS parentItemId')
            ->where('i.menu = :menu')
            ->setParameter('menu', $menu)
            ->getQuery()
            ->getArrayResult();

        /** @var array<int, list<int>> $childrenByParent */
        $childrenByParent = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cidRaw = $row['itemId'] ?? null;
            if ($cidRaw === null || $cidRaw === '') {
                continue;
            }
            $cid = (int) $cidRaw;
            if ($cid <= 0) {
                continue;
            }
            $pidRaw = $row['parentItemId'] ?? null;
            if ($pidRaw === null || $pidRaw === '') {
                continue;
            }
            $pid = (int) $pidRaw;
            if (!isset($childrenByParent[$pid])) {
                $childrenByParent[$pid] = [];
            }
            $childrenByParent[$pid][] = $cid;
        }

        /** @var list<int> $out */
        $out   = [$rootItemId];
        $queue = [$rootItemId];
        while ($queue !== []) {
            $current = array_shift($queue);
            if (!is_int($current)) {
                continue;
            }
            if (!isset($childrenByParent[$current])) {
                continue;
            }
            foreach ($childrenByParent[$current] as $childId) {
                $out[]   = $childId;
                $queue[] = $childId;
            }
        }

        return $out;
    }

    /**
     * Returns this item's id plus every descendant id (same menu). Used to exclude invalid parent
     * choices (a node cannot be parent of its ancestor). Prefer {@see findIdsInSubtreeStartingAt} when
     * menu + id are known. Falls back to in-memory children only when the item has no menu.
     *
     * @return list<int>
     */
    public function findIdsOfItemAndDescendants(MenuItem $item): array
    {
        $id = $item->getId();
        if ($id === null) {
            return [];
        }

        $menu = $item->getMenu();
        if ($menu instanceof Menu) {
            return $this->findIdsInSubtreeStartingAt($menu, (int) $id);
        }

        $ids = [(int) $id];
        foreach ($item->getChildren() as $child) {
            $ids = array_merge($ids, $this->findIdsOfItemAndDescendants($child));
        }

        return $ids;
    }

    /**
     * Query builder for menu items that can be chosen as parent (same menu, excluding given IDs).
     * Order by parent then position for consistent dropdown order.
     *
     * @param list<int> $excludeIds
     */
    public function getPossibleParentsQueryBuilder(Menu $menu, array $excludeIds = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.menu = :menu')
            ->setParameter('menu', $menu)
            ->orderBy('i.parent', 'ASC')
            ->addOrderBy('i.position', 'ASC');
        if ($excludeIds !== []) {
            $qb->andWhere('i.id NOT IN (:exclude)')
                ->setParameter('exclude', $excludeIds);
        }

        return $qb;
    }

    /**
     * Returns a map of menuId => itemCount for the given menus.
     *
     * @param list<int> $menuIds
     *
     * @return array<int, int>
     */
    public function countForMenus(array $menuIds): array
    {
        if ($menuIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.menu) AS menu_id, COUNT(i.id) AS item_count')
            ->where('i.menu IN (:menuIds)')
            ->setParameter('menuIds', $menuIds)
            ->groupBy('i.menu');

        $rows = $qb->getQuery()->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $menuId = isset($row['menu_id']) ? (int) $row['menu_id'] : 0;
            if ($menuId <= 0) {
                continue;
            }
            $out[$menuId] = isset($row['item_count']) ? (int) $row['item_count'] : 0;
        }

        return $out;
    }

    /**
     * Reassigns `position` for every item in the menu: within each sibling group (same parent),
     * keeps sort order by current position then id, then sets positions to step, 2*step, 3*step, ….
     *
     * @return int Number of items whose position value changed
     */
    public function reindexPositionsWithStep(Menu $menu, int $step): int
    {
        if ($step < 1) {
            throw new InvalidArgumentException('position step must be >= 1');
        }

        $items = $this->findAllForMenuOrderedByTreeForExport($menu);
        /** @var array<int, list<MenuItem>> $byParent */
        $byParent = [];
        foreach ($items as $item) {
            $pid = $item->getParent()?->getId() ?? -1;
            if (!isset($byParent[$pid])) {
                $byParent[$pid] = [];
            }
            $byParent[$pid][] = $item;
        }

        $changed = 0;
        foreach ($byParent as $group) {
            usort($group, static function (MenuItem $a, MenuItem $b): int {
                $byPos = $a->getPosition() <=> $b->getPosition();
                if ($byPos !== 0) {
                    return $byPos;
                }

                return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
            });
            foreach ($group as $i => $item) {
                $newPosition = $step * ($i + 1);
                if ($item->getPosition() !== $newPosition) {
                    $item->setPosition($newPosition);
                    ++$changed;
                }
            }
        }

        return $changed;
    }

    /**
     * Applies a full tree layout from a flat list of nodes (id, parent_id, position within siblings).
     * Validates menu ownership, counts, acyclicity, depth limit, and parent–descendant rules.
     *
     * @param list<array{id: int, parent_id: int|null, position: int}> $nodes
     */
    public function applyTreeLayout(Menu $menu, array $nodes, int $positionStep): void
    {
        if ($positionStep < 1) {
            throw new InvalidArgumentException('position step must be >= 1');
        }

        $items = $this->findAllForMenuOrderedByTreeForExport($menu);
        /** @var array<int, MenuItem> $byId */
        $byId = [];
        foreach ($items as $item) {
            $id = $item->getId();
            if ($id !== null) {
                $byId[$id] = $item;
            }
        }

        if ($nodes === []) {
            if ($byId === []) {
                return;
            }

            throw new InvalidArgumentException('tree payload is empty but menu has items');
        }

        if (!array_is_list($nodes)) {
            throw new InvalidArgumentException('tree payload must be a JSON array');
        }

        if (count($nodes) !== count($byId)) {
            throw new InvalidArgumentException('tree node count does not match menu item count');
        }

        /** @var array<int, int|null> $parentOf */
        $parentOf = [];
        /** @var array<string, list<array{id: int, parent_id: int|null, position: int}>> $byParentKey */
        $byParentKey = [];
        $seenIds     = [];

        foreach ($nodes as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('each tree node must be an object');
            }
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0 || !isset($byId[$id])) {
                throw new InvalidArgumentException('unknown or invalid item id in tree payload');
            }
            if (isset($seenIds[$id])) {
                throw new InvalidArgumentException('duplicate item id in tree payload');
            }
            $seenIds[$id] = true;

            $parentRaw = $row['parent_id'] ?? null;
            $parentId  = null;
            if ($parentRaw !== null && $parentRaw !== '') {
                $parentId = (int) $parentRaw;
                if ($parentId <= 0 || !isset($byId[$parentId])) {
                    throw new InvalidArgumentException('invalid parent id in tree payload');
                }
            }

            $position = isset($row['position']) ? (int) $row['position'] : 0;

            $parentOf[$id] = $parentId;
            $pkey          = $parentId === null ? '__root' : (string) $parentId;
            if (!isset($byParentKey[$pkey])) {
                $byParentKey[$pkey] = [];
            }
            $byParentKey[$pkey][] = ['id' => $id, 'parent_id' => $parentId, 'position' => $position];
        }

        if (count($seenIds) !== count($byId)) {
            throw new InvalidArgumentException('tree payload must include every menu item');
        }

        foreach ($parentOf as $itemId => $parentId) {
            if ($parentId === null) {
                continue;
            }
            if ($byId[$itemId]->getItemType() === MenuItem::ITEM_TYPE_SECTION) {
                throw new InvalidArgumentException(self::TREE_LAYOUT_SECTION_MUST_BE_ROOT);
            }
        }

        $cycle = ParentRelationCycleDetector::findFirstCycle($parentOf);
        if ($cycle !== null) {
            throw new InvalidArgumentException('tree payload introduces a parent cycle');
        }

        foreach ($parentOf as $itemId => $parentId) {
            if ($parentId === null) {
                continue;
            }
            if ($parentId === $itemId) {
                throw new InvalidArgumentException('item cannot be its own parent');
            }
            if ($this->isAncestorOfInParentMap($itemId, $parentId, $parentOf)) {
                throw new InvalidArgumentException('cannot set parent to a descendant of the item');
            }
        }

        $depthLimit = $menu->getDepthLimit();
        if ($depthLimit !== null && $depthLimit >= 1) {
            $maxDepthIndex = $depthLimit - 1;
            foreach (array_keys($parentOf) as $itemId) {
                $depth = $this->computeDepthInParentMap((int) $itemId, $parentOf, []);
                if ($depth > $maxDepthIndex) {
                    throw new InvalidArgumentException('tree exceeds menu depth limit');
                }
            }
        }

        /** @var list<int> $idsByDepthDesc */
        $idsByDepthDesc = array_keys($parentOf);
        usort($idsByDepthDesc, function (int $a, int $b) use ($parentOf): int {
            $da = $this->computeDepthInParentMap($a, $parentOf, []);
            $db = $this->computeDepthInParentMap($b, $parentOf, []);

            return $db <=> $da;
        });

        foreach ($idsByDepthDesc as $itemId) {
            $pid    = $parentOf[$itemId];
            $parent = $pid === null ? null : $byId[$pid];
            $byId[$itemId]->setParent($parent);
        }

        foreach ($byParentKey as &$group) {
            usort($group, static function (array $a, array $b): int {
                $cmp = $a['position'] <=> $b['position'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                return $a['id'] <=> $b['id'];
            });
        }
        unset($group);

        ksort($byParentKey);
        foreach ($byParentKey as $group) {
            foreach ($group as $i => $row) {
                $byId[$row['id']]->setPosition($positionStep * ($i + 1));
            }
        }
    }

    /**
     * True if `$ancestorId` is on the path from `$startId` upward (i.e. `$startId` is in the subtree of `$ancestorId`).
     *
     * @param array<int, int|null> $parentOf
     */
    private function isAncestorOfInParentMap(int $ancestorId, int $startId, array $parentOf): bool
    {
        $current = $startId;
        $guard   = 0;
        while ($guard++ <= count($parentOf) + 2) {
            if ($current === $ancestorId) {
                return true;
            }
            $next = $parentOf[$current] ?? null;
            if ($next === null) {
                return false;
            }
            $current = $next;
        }

        return false;
    }

    /**
     * @param array<int, int|null> $parentOf
     * @param array<int, int> $memo
     */
    private function computeDepthInParentMap(int $id, array $parentOf, array &$memo): int
    {
        if (isset($memo[$id])) {
            return $memo[$id];
        }
        $p = $parentOf[$id] ?? null;
        if ($p === null) {
            return $memo[$id] = 0;
        }

        return $memo[$id] = 1 + $this->computeDepthInParentMap($p, $parentOf, $memo);
    }

    /**
     * Returns one parent-id cycle if the menu items form a circular parent chain; otherwise null.
     * Uses only persisted id and parent_id (same menu).
     *
     * @return list<int>|null Ordered item ids along one cycle (e.g. [1,2,3] for 1→2→3→1)
     */
    public function findFirstParentIdCycleChain(Menu $menu): ?array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.id AS id')
            ->addSelect('IDENTITY(i.parent) AS parentId')
            ->where('i.menu = :menu')
            ->setParameter('menu', $menu)
            ->getQuery()
            ->getArrayResult();

        /** @var array<int, int|null> $parentOf */
        $parentOf = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $pidRaw        = $row['parentId'] ?? null;
            $parentOf[$id] = ($pidRaw === null || $pidRaw === '') ? null : (int) $pidRaw;
        }

        return ParentRelationCycleDetector::findFirstCycle($parentOf);
    }
}
