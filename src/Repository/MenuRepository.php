<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\EventSubscriber\DashboardMenuWorkerStateSubscriber;
use Nowo\DashboardMenuBundle\EventSubscriber\MenuCacheInvalidationSubscriber;
use SortDirection;
use Symfony\Contracts\Service\ResetInterface;

use function array_key_exists;
use function count;
use function is_array;

/**
 * @extends ServiceEntityRepository<Menu>
 *
 * {@see findOneByCodeAndContext} is memoized for the request lifetime (Doctrine does not
 * dedupe identical {@see findOneBy} lookups). The memo is cleared at the start of every main
 * request by {@see DashboardMenuWorkerStateSubscriber}
 * and by `kernel.reset`, so long-lived workers never keep it across requests.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class MenuRepository extends ServiceEntityRepository implements ResetInterface
{
    /** @var array<string, Menu|null> */
    private array $byCodeContext = [];

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
     * Results are memoized until {@see reset()} (end of request, or after menu write
     * invalidation via {@see MenuCacheInvalidationSubscriber}).
     *
     * @param array<string, bool|int|string>|null $context
     */
    public function findOneByCodeAndContext(string $code, ?array $context): ?Menu
    {
        $key = $code . "\0" . Menu::canonicalContextKey($context);
        if (array_key_exists($key, $this->byCodeContext)) {
            return $this->byCodeContext[$key];
        }

        $contextKey = Menu::canonicalContextKey($context);

        return $this->byCodeContext[$key] = $this->findOneBy(['code' => $code, 'contextKey' => $contextKey]);
    }

    public function reset(): void
    {
        $this->byCodeContext = [];
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
            . ' class_section_label, class_section, class_divider,'
            . ' class_current, class_branch_expanded, class_has_children, class_expanded, class_collapsed,'
            . ' permission_checker, depth_limit, collapsible, collapsible_expanded,'
            . ' nested_collapsible, nested_collapsible_sections, attributes, base';
        $sql      = "SELECT {$menuColumns} FROM {$quotedMenu} WHERE code = ? AND attributes_key IN ({$placeholders})";
        $params   = array_merge([$code], $contextKeys);
        $menuRows = $conn->fetchAllAssociative($sql, $params);
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
        $menuRow ??= $menuRows[0];

        $menuId = $menuRow['id'] ?? null;
        if ($menuId === null) {
            return null;
        }

        $quotedItem  = $this->quoteTableName($conn, $itemTable);
        $itemColumns = 'id, menu_id, parent_id, position, label, translations, link_type, route_name, route_params,'
            . ' external_url, permission_key, permission_keys, is_unanimous, icon, item_type,'
            . ' link_resolver, target_blank, section_collapsible';
        $itemsSql = "SELECT {$itemColumns} FROM {$quotedItem} WHERE menu_id = ? ORDER BY parent_id ASC, position ASC";
        $itemRows = $conn->fetchAllAssociative($itemsSql, [$menuId]);

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
     * Takes the write lock of one menu row (`SELECT … FOR UPDATE`) until the current transaction ends (2.23,
     * REQ-RUNTIME-003 rule 4). Every write to a menu's item tree (append, move, reorder, re-index, delete, import
     * replace) takes it first, inside its write unit, so two writers of one menu run one after the other and
     * the sibling position read after it (`MAX(position) + 1`) cannot be read twice. One lock per unit, always the
     * menu row, so the bundle's own writers cannot deadlock each other. Waits at most the server's lock wait timeout
     * (`innodb_lock_wait_timeout` / `lock_timeout`), then fails as a lock conflict (409 `CONFLICT_001`).
     *
     * Must run inside a transaction (Doctrine refuses a pessimistic lock outside one). No entity is hydrated, so a
     * managed menu keeps its pending form edits. SQLite has no row locks (its writers are serialised by the database
     * lock).
     *
     * @return bool false when the menu row does not exist (deleted meanwhile, or never flushed)
     */
    public function lockForWrite(Menu|int $menu): bool
    {
        $id = $menu instanceof Menu ? $menu->getId() : $menu;
        if ($id === null) {
            return false;
        }

        $row = $this->createQueryBuilder('m')
            ->select('m.id')
            ->where('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return $row !== null;
    }

    /**
     * The stored `edit_version` of one menu, read from the database (never the unit of work) under a `PESSIMISTIC_WRITE`
     * lock of its row (2.24, REQ-RUNTIME-003 rule 4). Must run inside a write unit. Null when the row is gone.
     */
    public function storedEditVersion(int $id): ?int
    {
        $row = $this->createQueryBuilder('m')
            ->select('m.editVersion')
            ->where('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return is_array($row) ? (int) $row['editVersion'] : null;
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
            ->orderBy('m.code', SortDirection::Ascending);
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
