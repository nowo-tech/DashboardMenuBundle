<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\TransactionRequiredException;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Concurrency\DashboardMenuTransaction;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuTreeCacheInvalidator;
use Nowo\DashboardMenuBundle\Tests\Kernel\TestKernel;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * 2.23 (REQ-RUNTIME-003) on SQLite: the write unit, the menu row lock and the locking item read through the real
 * container wiring. The two-connection races run on MySQL / MariaDB / PostgreSQL (tests/Integration/Database).
 */
final class MenuWriteUnitIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

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
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    public function testTheUnitCommitsAndTheLockSeesTheMenuRow(): void
    {
        $unit = self::getContainer()->get(DashboardMenuTransaction::class);
        self::assertInstanceOf(DashboardMenuTransaction::class, $unit);
        $menus = $this->menus();
        $items = $this->items();

        $menu = $unit->run(static function (EntityManagerInterface $em): Menu {
            $menu = new Menu();
            $menu->setCode('sidebar');
            $em->persist($menu);

            return $menu;
        });
        self::assertNotNull($menu->getId());

        $locked = $unit->run(static function (EntityManagerInterface $em) use ($menu, $menus, $items): array {
            $found = $menus->lockForWrite($menu);
            $item  = new MenuItem();
            $item->setMenu($menu);
            $item->setLabel('One');
            $item->setPosition($items->findMaxPositionForParent($menu, null) + 1);
            $em->persist($item);
            $em->flush();

            return [$found, $menus->lockForWrite((int) $menu->getId() + 100), $menus->lockForWrite(new Menu()), $items->findAllForMenuForUpdate($menu)];
        });

        self::assertTrue($locked[0]);
        self::assertFalse($locked[1], 'an id without a row');
        self::assertFalse($locked[2], 'a menu that was never flushed');
        self::assertCount(1, $locked[3]);
        self::assertSame(0, $this->entityManager->getConnection()->getTransactionNestingLevel());
    }

    public function testAWriteBumpsTheTreeCacheVersionInTheFlushAndAgainAfterTheCommit(): void
    {
        $unit = self::getContainer()->get(DashboardMenuTransaction::class);
        self::assertInstanceOf(DashboardMenuTransaction::class, $unit);
        $invalidator = self::getContainer()->get(MenuTreeCacheInvalidator::class);
        self::assertInstanceOf(MenuTreeCacheInvalidator::class, $invalidator);
        // A code of its own: the test kernel's cache pool outlives one run.
        $code   = 'cache-' . bin2hex(random_bytes(6));
        $before = $invalidator->getVersionForMenuCode($code);
        self::assertNull($this->menus()->findOneByCodeAndContext($code, null));

        $unit->run(static function (EntityManagerInterface $em) use ($code): void {
            $menu = new Menu();
            $menu->setCode($code);
            $em->persist($menu);
        });

        self::assertSame($before + 2, $invalidator->getVersionForMenuCode($code), 'postPersist bump + after-commit bump');
        self::assertInstanceOf(Menu::class, $this->menus()->findOneByCodeAndContext($code, null), 'the lookup memo was cleared');
    }

    public function testTheLockNeedsATransaction(): void
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $this->expectException(TransactionRequiredException::class);
        $this->menus()->lockForWrite($menu);
    }

    public function testTheStoredEditVersionIsReadFromTheDatabaseUnderTheRowLock(): void
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $item = new MenuItem();
        $item->setMenu($menu)->setLabel('One');
        $this->entityManager->persist($menu);
        $this->entityManager->persist($item);
        $this->entityManager->flush();
        $menuId = (int) $menu->getId();
        $itemId = (int) $item->getId();
        $unit   = self::getContainer()->get(DashboardMenuTransaction::class);
        self::assertInstanceOf(DashboardMenuTransaction::class, $unit);
        $items = $this->entityManager->getRepository(MenuItem::class);
        self::assertInstanceOf(MenuItemRepository::class, $items);

        // Another request saved: the row says 5 while the managed entity still says 1.
        $this->entityManager->getConnection()->executeStatement('UPDATE dashboard_menu SET edit_version = 5 WHERE id = ?', [$menuId]);
        $this->entityManager->getConnection()->executeStatement('UPDATE dashboard_menu_item SET edit_version = 6 WHERE id = ?', [$itemId]);

        $read = $unit->run(fn (): array => [$this->menus()->storedEditVersion($menuId), $items->storedEditVersion($itemId), $this->menus()->storedEditVersion(999), $items->storedEditVersion(999)]);

        self::assertSame([5, 6, null, null], $read);
        self::assertSame(1, $menu->getEditVersion());
    }

    public function testASubmitTokenIsFoundOnceItsItemIsStored(): void
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $item = new MenuItem();
        $item->setMenu($menu)->setLabel('One')->setSubmitToken('formtoken0000000000000000000001');
        $this->entityManager->persist($menu);
        $this->entityManager->persist($item);
        $this->entityManager->flush();
        $items = $this->entityManager->getRepository(MenuItem::class);
        self::assertInstanceOf(MenuItemRepository::class, $items);

        self::assertTrue($items->submitTokenExists('formtoken0000000000000000000001'));
        self::assertFalse($items->submitTokenExists('ffffffffffffffffffffffffffffffff'));
    }

    public function testTheSubmitTokenOfTwoItemsIsUnique(): void
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $this->entityManager->persist($menu);
        foreach (['One', 'Two'] as $label) {
            $item = new MenuItem();
            $item->setMenu($menu)->setLabel($label)->setSubmitToken('formtoken0000000000000000000001');
            $this->entityManager->persist($item);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testAFailedUnitRollsBackAndForgetsTheMenuEntitiesOnly(): void
    {
        $unit = self::getContainer()->get(DashboardMenuTransaction::class);
        self::assertInstanceOf(DashboardMenuTransaction::class, $unit);

        $caught = null;
        try {
            $unit->run(static function (EntityManagerInterface $em): never {
                $menu = new Menu();
                $menu->setCode('sidebar');
                $em->persist($menu);
                $em->flush();

                throw new RuntimeException('refused');
            });
        } catch (RuntimeException $failure) {
            $caught = $failure->getMessage();
        }
        self::assertSame('refused', $caught);

        self::assertTrue($this->entityManager->isOpen());
        self::assertSame([], $this->entityManager->getUnitOfWork()->getIdentityMap()[Menu::class] ?? []);
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_menu'));
    }

    private function menus(): MenuRepository
    {
        $registry = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $repository = $registry->getRepository(Menu::class);
        self::assertInstanceOf(MenuRepository::class, $repository);

        return $repository;
    }

    private function items(): MenuItemRepository
    {
        $registry = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $repository = $registry->getRepository(MenuItem::class);
        self::assertInstanceOf(MenuItemRepository::class, $repository);

        return $repository;
    }
}
