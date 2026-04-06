<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;

use function count;

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
     * Loads menu and all its items in two SQL queries (no N+1). Returns raw rows for caching.
     * First query: menu by code and context_keys; second: items by menu_id ordered by parent_id, position.
     *
     * @param list<array<string, bool|int|string>|null> $contextSets
     *
     * @return array{menu: array<string, mixed>, items: list<array<string, mixed>>}|null
     */
    public function findMenuAndItemsRaw(string $code, array $contextSets): ?array
    {
        $em        = $this->getEntityManager();
        $conn      = $em->getConnection();
        $meta      = $em->getClassMetadata(Menu::class);
        $menuTable = $meta->getTableName();
        $itemMeta  = $em->getClassMetadata(MenuItem::class);
        $itemTable = $itemMeta->getTableName();

        $contextKeys = [];
        foreach ($contextSets as $ctx) {
            $contextKeys[] = Menu::canonicalContextKey($ctx);
        }
        if ($contextKeys === []) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($contextKeys), '?'));
        $quotedMenu   = $this->quoteTableName($conn, $menuTable);
        $menuColumns  = 'id, code, attributes_key, name, icon, class_menu, ul_id, class_item, class_link,'
            . ' class_children, class_section_children, class_section_child_item, class_section_child_link,'
            . ' class_current, class_branch_expanded, class_has_children, class_expanded, class_collapsed,'
            . ' permission_checker, depth_limit, collapsible, collapsible_expanded,'
            . ' nested_collapsible, nested_collapsible_sections, attributes, base';
        $sql          = "SELECT {$menuColumns} FROM {$quotedMenu} WHERE code = ? AND attributes_key IN ({$placeholders})";
        $params       = array_merge([$code], $contextKeys);
        $menuRows     = $conn->fetchAllAssociative($sql, $params);
        if ($menuRows === []) {
            return null;
        }

        $menuRow = null;
        foreach ($contextSets as $ctx) {
            $wantKey = Menu::canonicalContextKey($ctx);
            foreach ($menuRows as $row) {
                $key = $row['attributes_key'] ?? '';
                if ($key === $wantKey) {
                    $menuRow = $row;
                    break 2;
                }
            }
        }
        if ($menuRow === null) {
            $menuRow = $menuRows[0];
        }

        $menuId = $menuRow['id'] ?? null;
        if ($menuId === null) {
            return null;
        }

        $quotedItem  = $this->quoteTableName($conn, $itemTable);
        $itemColumns = 'id, menu_id, parent_id, position, label, translations, link_type, route_name, route_params,'
            . ' external_url, permission_key, permission_keys, is_unanimous, icon, item_type,'
            . ' link_resolver, target_blank, section_collapsible';
        $itemsSql    = "SELECT {$itemColumns} FROM {$quotedItem} WHERE menu_id = ? ORDER BY parent_id ASC, position ASC";
        $itemRows   = $conn->fetchAllAssociative($itemsSql, [$menuId]);

        return ['menu' => $menuRow, 'items' => $itemRows];
    }

    /**
     * Quotes a table name for raw SQL using the database platform (avoids deprecated Connection::quoteIdentifier).
     */
    private function quoteTableName(Connection $conn, string $tableName): string
    {
        return $conn->getDatabasePlatform()->quoteSingleIdentifier($tableName);
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
