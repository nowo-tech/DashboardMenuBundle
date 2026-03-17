<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;

use function assert;
use function is_array;

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
        $result = $qb->getQuery()->getResult();
        assert(is_array($result) && array_is_list($result));

        /* @var list<MenuItem> $result */
        return $result;
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
}
