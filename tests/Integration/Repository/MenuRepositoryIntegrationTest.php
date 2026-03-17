<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Tests\Kernel\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MenuRepositoryIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private MenuRepository $repository;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->createSchema();
        $this->repository = $this->entityManager->getRepository(Menu::class);
    }

    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    private function createSchema(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        (new SchemaTool($this->entityManager))->createSchema($metadata);
    }

    public function testFindOneByCodeReturnsNullWhenEmpty(): void
    {
        self::assertNull($this->repository->findOneByCode('sidebar'));
    }

    public function testFindOneByCodeAndContextReturnsMenuWhenMatch(): void
    {
        $menu = new Menu();
        $menu->setCode('nav');
        $menu->setContext(null);
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $found = $this->repository->findOneByCodeAndContext('nav', null);
        self::assertInstanceOf(Menu::class, $found);
        self::assertSame('nav', $found->getCode());
    }

    public function testFindOneByCodeReturnsFirstMatch(): void
    {
        $menu = new Menu();
        $menu->setCode('nav');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        self::assertInstanceOf(Menu::class, $this->repository->findOneByCode('nav'));
    }

    public function testFindForCodeWithContextSetsTriesEachSet(): void
    {
        $menu = new Menu();
        $menu->setCode('x');
        $menu->setContext(['p' => '1']);
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        self::assertNull($this->repository->findForCodeWithContextSets('x', [null, []]));
        self::assertInstanceOf(Menu::class, $this->repository->findForCodeWithContextSets('x', [['p' => '1']]));
    }

    public function testFindOneById(): void
    {
        $menu = new Menu();
        $menu->setCode('id-test');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $id = $menu->getId();
        self::assertNotNull($id);
        $found = $this->repository->findOneById($id);
        self::assertInstanceOf(Menu::class, $found);
        self::assertSame($id, $found->getId());
    }

    public function testFindAllOrderedByCode(): void
    {
        foreach (['b', 'a', 'c'] as $code) {
            $m = new Menu();
            $m->setCode($code);
            $this->entityManager->persist($m);
        }
        $this->entityManager->flush();

        $all = $this->repository->findAllOrderedByCode();
        self::assertCount(3, $all);
        self::assertSame('a', $all[0]->getCode());
        self::assertSame('b', $all[1]->getCode());
        self::assertSame('c', $all[2]->getCode());
    }

    public function testCreateSearchQueryBuilderWithSearch(): void
    {
        $qb = $this->repository->createSearchQueryBuilder('foo');
        self::assertNotNull($qb->getDQLPart('where'));
    }

    public function testCreateSearchQueryBuilderEmptySearch(): void
    {
        $qb = $this->repository->createSearchQueryBuilder('');
        self::assertEmpty($qb->getDQLPart('where'));
    }

    public function testFindForDashboardWithPagination(): void
    {
        $menu = new Menu();
        $menu->setCode('dash');
        $menu->setName('Dashboard');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $list = $this->repository->findForDashboard('', 0, 10);
        self::assertCount(1, $list);
        $list = $this->repository->findForDashboard('', 0);
        self::assertCount(1, $list);
    }

    public function testCountForDashboard(): void
    {
        self::assertSame(0, $this->repository->countForDashboard(''));
        $menu = new Menu();
        $menu->setCode('c');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();
        self::assertSame(1, $this->repository->countForDashboard(''));
        self::assertSame(1, $this->repository->countForDashboard('c'));
        self::assertSame(0, $this->repository->countForDashboard('nonexistent'));
    }

    public function testFindMenuAndItemsRawReturnsNullWhenContextSetsEmpty(): void
    {
        self::assertNull($this->repository->findMenuAndItemsRaw('nav', []));
    }

    public function testFindMenuAndItemsRawReturnsNullWhenNoMenuMatches(): void
    {
        self::assertNull($this->repository->findMenuAndItemsRaw('unknown', [null, []]));
    }

    public function testFindMenuAndItemsRawReturnsMenuAndItems(): void
    {
        $menu = new Menu();
        $menu->setCode('raw');
        $menu->setContext(null);
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setLabel('Item1');
        $item->setPosition(0);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        $raw = $this->repository->findMenuAndItemsRaw('raw', [null, []]);
        self::assertIsArray($raw);
        self::assertArrayHasKey('menu', $raw);
        self::assertArrayHasKey('items', $raw);
        self::assertSame('raw', $raw['menu']['code'] ?? null);
        self::assertCount(1, $raw['items']);
        self::assertSame('Item1', $raw['items'][0]['label'] ?? null);
    }
}
