<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Tests\Kernel\TestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * REQ-TEST-003: tree layout, position reindexing, cycle detection and subtree lookup of the item repository (real
 * SQLite through the test kernel).
 */
final class MenuItemRepositoryTreeLayoutIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private MenuItemRepository $repository;

    private Menu $menu;

    /** @var array<string, MenuItem> */
    private array $items = [];

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $repository = $this->entityManager->getRepository(MenuItem::class);
        self::assertInstanceOf(MenuItemRepository::class, $repository);
        $this->repository = $repository;

        $this->menu = (new Menu())->setCode('nav');
        $this->entityManager->persist($this->menu);
        $this->entityManager->flush();
        // a (root) > b ; c (root) ; s (section, root)
        $this->items['a'] = $this->item('A', 20);
        $this->items['b'] = $this->item('B', 5, $this->items['a']);
        $this->items['c'] = $this->item('C', 20);
        $this->items['s'] = $this->item('S', 40, null, MenuItem::ITEM_TYPE_SECTION);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    public function testReindexPositionsRenumbersEverySiblingGroupAndCountsTheChanges(): void
    {
        self::assertSame(3, $this->repository->reindexPositionsWithStep($this->menu, 10));
        self::assertSame(10, $this->items['a']->getPosition());
        self::assertSame(20, $this->items['c']->getPosition(), 'ties are broken by id');
        self::assertSame(30, $this->items['s']->getPosition());
        self::assertSame(10, $this->items['b']->getPosition());

        self::assertSame(0, $this->repository->reindexPositionsWithStep($this->menu, 10), 'already renumbered');
    }

    public function testReindexAndApplyTreeLayoutRejectAStepBelowOne(): void
    {
        foreach ([
            fn (): int => $this->repository->reindexPositionsWithStep($this->menu, 0),
            fn () => $this->repository->applyTreeLayout($this->menu, [], 0),
        ] as $call) {
            try {
                $call();
                self::fail('A step below 1 must be refused.');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('step must be >= 1', $e->getMessage());
            }
        }
    }

    public function testApplyTreeLayoutMovesItemsAndRenumbersThem(): void
    {
        $this->repository->applyTreeLayout($this->menu, [
            ['id' => $this->id('c'), 'parent_id' => null, 'position' => 0],
            ['id' => $this->id('a'), 'parent_id' => null, 'position' => 1],
            ['id' => $this->id('b'), 'parent_id' => (string) $this->id('c'), 'position' => '3'],
            ['id' => (string) $this->id('s'), 'parent_id' => '', 'position' => 1],
        ], 100);
        $this->entityManager->flush();

        self::assertSame($this->items['c'], $this->items['b']->getParent());
        self::assertSame(100, $this->items['c']->getPosition());
        self::assertSame(200, $this->items['a']->getPosition());
        self::assertSame(100, $this->items['b']->getPosition());
        self::assertSame(300, $this->items['s']->getPosition(), 'equal positions are ordered by id (a before s)');
    }

    public function testAnEmptyPayloadIsFineForAnEmptyMenuAndRefusedOtherwise(): void
    {
        $empty = (new Menu())->setCode('empty');
        $this->entityManager->persist($empty);
        $this->entityManager->flush();

        $this->repository->applyTreeLayout($empty, [], 10);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tree payload is empty but menu has items');
        $this->repository->applyTreeLayout($this->menu, [], 10);
    }

    /**
     * @param callable(self): array<mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadsAreRefused(callable $payload, string $message): void
    {
        $this->items['b']->setParent($this->items['a']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->repository->applyTreeLayout($this->menu, $payload($this), 10);
    }

    /**
     * @return iterable<string, array{callable(self): array<mixed>, string}>
     */
    public static function invalidPayloads(): iterable
    {
        $node = static fn (self $t, string $key, ?int $parent = null): array => ['id' => $t->id($key), 'parent_id' => $parent, 'position' => 1];
        $all  = static fn (self $t): array => [$node($t, 'a'), $node($t, 'b', $t->id('a')), $node($t, 'c'), $node($t, 's')];

        yield 'not a list' => [static fn (self $t): array => ['x' => $node($t, 'a')], 'must be a JSON array'];
        yield 'wrong node count' => [static fn (self $t): array => [$node($t, 'a')], 'node count does not match'];
        yield 'a node that is not an object' => [static fn (self $t): array => ['x', 'y', 'z', 'w'], 'each tree node must be an object'];
        yield 'an unknown id' => [static fn (self $t): array => [$node($t, 'a'), $node($t, 'b'), $node($t, 'c'), ['id' => 99999, 'parent_id' => null, 'position' => 1]], 'unknown or invalid item id'];
        yield 'a missing id' => [static fn (self $t): array => [$node($t, 'a'), $node($t, 'b'), $node($t, 'c'), ['parent_id' => null]], 'unknown or invalid item id'];
        yield 'a duplicate id' => [static fn (self $t): array => [$node($t, 'a'), $node($t, 'b'), $node($t, 'c'), $node($t, 'c')], 'duplicate item id'];
        yield 'an unknown parent' => [static fn (self $t): array => [$node($t, 'a', 99999), $node($t, 'b'), $node($t, 'c'), $node($t, 's')], 'invalid parent id'];
        yield 'a section below another item' => [static fn (self $t): array => [$node($t, 'a'), $node($t, 'b'), $node($t, 'c'), $node($t, 's', $t->id('a'))], 'section'];
        yield 'a cycle' => [static fn (self $t): array => [$node($t, 'a', $t->id('b')), $node($t, 'b', $t->id('a')), $node($t, 'c'), $node($t, 's')], 'parent cycle'];
        yield 'beyond the depth limit' => [static function (self $t) use ($all): array {
            $t->limitDepth(1);

            return $all($t);
        }, 'depth limit'];
    }

    public function testFindFirstParentIdCycleChainFindsAStoredCycle(): void
    {
        self::assertNull($this->repository->findFirstParentIdCycleChain($this->menu));

        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('UPDATE ' . $this->table() . ' SET parent_id = ? WHERE id = ?', [$this->id('b'), $this->id('a')]);

        $cycle = $this->repository->findFirstParentIdCycleChain($this->menu);
        self::assertEqualsCanonicalizing([$this->id('a'), $this->id('b')], $cycle);
    }

    public function testSubtreeIdsOfAPersistedAndOfADetachedItem(): void
    {
        self::assertEqualsCanonicalizing([$this->id('a'), $this->id('b')], $this->repository->findIdsOfItemAndDescendants($this->items['a']));
        self::assertSame([], $this->repository->findIdsOfItemAndDescendants(new MenuItem()), 'an item without id has no subtree');

        $detachedParent = new MenuItem();
        $detachedChild  = new MenuItem();
        $detachedChild->setParent($detachedParent);
        $detachedParent->getChildren()->add($detachedChild);
        $ref = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($detachedParent, 700);
        $ref->setValue($detachedChild, 701);
        self::assertSame([700, 701], $this->repository->findIdsOfItemAndDescendants($detachedParent), 'without a menu the in-memory children are followed');
    }

    public function id(string $key): int
    {
        return (int) $this->items[$key]->getId();
    }

    public function limitDepth(int $limit): void
    {
        $this->menu->setDepthLimit($limit);
    }

    private function item(string $label, int $position, ?MenuItem $parent = null, string $type = MenuItem::ITEM_TYPE_LINK): MenuItem
    {
        $item = (new MenuItem())->setMenu($this->menu);
        $item->setLabel($label);
        $item->setPosition($position);
        $item->setItemType($type);
        if ($parent instanceof MenuItem) {
            $item->setParent($parent);
        }
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    private function table(): string
    {
        return $this->entityManager->getClassMetadata(MenuItem::class)->getTableName();
    }
}
