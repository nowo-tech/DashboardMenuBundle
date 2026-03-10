<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Entity\Menu;

/**
 * @extends ServiceEntityRepository<Menu>
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    /**
     * Finds one menu by code. When multiple menus share the same code (different context),
     * returns the first match with no context (empty), otherwise the first by id.
     */
    public function findOneByCode(string $code): ?Menu
    {
        return $this->findForCodeWithContextSets($code, [null, []]);
    }

    /**
     * Finds one menu by code and exact context match (null or [] = no context).
     *
     * @param array<string, bool|int|string>|null $context
     */
    public function findOneByCodeAndContext(string $code, ?array $context): ?Menu
    {
        $key = Menu::canonicalContextKey($context);

        return $this->findOneBy(['code' => $code, 'contextKey' => $key]);
    }

    /**
     * Tries each context set in order and returns the first menu that matches (code + context).
     * Each element of $contextSets is an array or null (meaning no context / empty).
     *
     * @param list<array<string, bool|int|string>|null> $contextSets
     */
    public function findForCodeWithContextSets(string $code, array $contextSets): ?Menu
    {
        foreach ($contextSets as $ctx) {
            $menu = $this->findOneByCodeAndContext($code, $ctx);
            if ($menu instanceof Menu) {
                return $menu;
            }
        }

        return null;
    }

    /**
     * Finds a menu by id (for dashboard when code can repeat).
     */
    public function findOneById(int $id): ?Menu
    {
        return $this->find($id);
    }

    /**
     * @return list<Menu>
     */
    public function findAllOrderedByCode(): array
    {
        return $this->findBy([], ['code' => 'ASC']);
    }

    /**
     * Query builder for dashboard list with optional search on code and name.
     */
    public function createSearchQueryBuilder(string $search = ''): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.code', 'ASC');
        if ($search !== '') {
            $term = '%' . addcslashes($search, '%_') . '%';
            $qb->andWhere('m.code LIKE :term OR m.name LIKE :term')
                ->setParameter('term', $term);
        }

        return $qb;
    }

    /**
     * Menus for dashboard list with optional search and pagination.
     *
     * @return list<Menu>
     */
    public function findForDashboard(string $search = '', int $offset = 0, ?int $limit = null): array
    {
        $qb = $this->createSearchQueryBuilder($search);
        if ($limit !== null && $limit > 0) {
            $qb->setFirstResult($offset)->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countForDashboard(string $search = ''): int
    {
        $qb = $this->createSearchQueryBuilder($search)
            ->select('COUNT(m.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
