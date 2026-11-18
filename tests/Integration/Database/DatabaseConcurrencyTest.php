<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Concurrency\DashboardMenuConflictAnswer;
use Nowo\DashboardMenuBundle\Concurrency\DashboardMenuTransaction;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

use function array_keys;
use function array_map;
use function getenv;
use function implode;
use function is_string;
use function json_encode;
use function random_int;
use function sort;
use function str_ends_with;
use function str_repeat;
use function str_replace;
use function strip_tags;
use function substr;

/**
 * REQ-RUNTIME-003 (2.23) on real servers: MySQL 8.4, MariaDB 11.4 and PostgreSQL 16, two connections, never simulated.
 *
 * Each server is named by an env var (DM_MYSQL_URL, DM_MARIADB_URL, DM_POSTGRES_URL); a missing one skips its cases
 * (the CI job "Databases" sets all three and runs with --fail-on-skipped). Every session sets a 1-second lock wait
 * (`innodb_lock_wait_timeout` / `lock_timeout`), so a lock the test holds makes the other side fail fast instead of
 * hanging the machine.
 *
 * - concurrent appends of one sibling group: the second waits for the menu row lock, answers 409 CONFLICT_001 with
 *   Retry-After while the first holds it, then gets the next position (no duplicate), on the host's manager and on the
 *   bundle's own manager;
 * - a host commit earlier in the same request turns the conflict into CONFLICT_002 without Retry-After;
 * - an import "replace" is one unit: a failure in the middle leaves the old tree, a held lock leaves it untouched;
 * - a concurrent create of the same code + context: the unique key refuses it, the form error / import error is the
 *   answer (no CONFLICT code), nothing half-imported;
 * - after every conflict the connection and the manager are usable.
 */
#[Group('database')]
final class DatabaseConcurrencyTest extends TestCase
{
    /** @var list<DatabaseTestKernel> */
    private array $kernels = [];

    /** @var list<Connection> */
    private array $connections = [];

    /**
     * @return iterable<string, array{string}>
     */
    public static function databases(): iterable
    {
        yield 'mysql 8.4' => ['DM_MYSQL_URL'];
        yield 'mariadb 11.4' => ['DM_MARIADB_URL'];
        yield 'postgresql 16' => ['DM_POSTGRES_URL'];
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $connection->close();
        }
        foreach ($this->kernels as $kernel) {
            $cache = $kernel->getCacheDir();
            $kernel->shutdown();
            (new Filesystem())->remove($cache . '/..');
        }
        $this->kernels     = [];
        $this->connections = [];
    }

    #[DataProvider('databases')]
    public function testConcurrentAppendsOnTheHostManagerGetDistinctPositions(string $env): void
    {
        $this->assertConcurrentAppends($this->url($env), DatabaseTestKernel::STANDARD);
    }

    #[DataProvider('databases')]
    public function testConcurrentAppendsOnTheOwnManagerGetDistinctPositions(string $env): void
    {
        $this->assertConcurrentAppends($this->url($env), DatabaseTestKernel::DEDICATED);
    }

    #[DataProvider('databases')]
    public function testHostCommitEarlierInTheRequestAnswersConflict002(string $env): void
    {
        $url    = $this->url($env);
        $kernel = $this->boot($url, DatabaseTestKernel::STANDARD, 'a', true);
        $this->createSchema($kernel);
        $menuId = $this->createMenu($kernel, 'sidebar');
        $client = $this->client($kernel);
        $fields = $this->newItemForm($client, $menuId);

        $holder = $this->connection($url);
        $holder->beginTransaction();
        $holder->fetchOne('SELECT id FROM dashboard_menu WHERE id = ? FOR UPDATE', [$menuId]);

        $client->request('POST', '/admin/menus/' . $menuId . '/item/new', $fields, [], ['HTTP_' . str_replace('-', '_', EarlierCommitListener::HEADER) => '1']);
        $response = $client->getResponse();
        self::assertSame(409, $response->getStatusCode());
        self::assertSame(ConflictCode::COMMITTED, $response->headers->get('X-Conflict-Code'));
        self::assertFalse($response->headers->has('Retry-After'));

        $holder->rollBack();
        self::assertSame([], $this->positions($kernel, $menuId));
        $this->assertUsable($kernel);
    }

    #[DataProvider('databases')]
    public function testConflictStatusCanBe503(string $env): void
    {
        $url    = $this->url($env);
        $kernel = $this->boot($url, DatabaseTestKernel::STANDARD, 'a', false, ['status' => 503, 'retry_after' => 7]);
        $this->createSchema($kernel);
        $menuId = $this->createMenu($kernel, 'sidebar');
        $client = $this->client($kernel);
        $fields = $this->newItemForm($client, $menuId);

        $holder = $this->connection($url);
        $holder->beginTransaction();
        $holder->fetchOne('SELECT id FROM dashboard_menu WHERE id = ? FOR UPDATE', [$menuId]);

        $client->request('POST', '/admin/menus/' . $menuId . '/item/new', $fields);
        $response = $client->getResponse();
        self::assertSame(503, $response->getStatusCode());
        self::assertSame(ConflictCode::RETRY_SAFE, $response->headers->get('X-Conflict-Code'));
        self::assertSame('7', $response->headers->get('Retry-After'));
        $holder->rollBack();
    }

    #[DataProvider('databases')]
    public function testImportReplaceIsOneUnit(string $env): void
    {
        $url    = $this->url($env);
        $kernel = $this->boot($url, DatabaseTestKernel::STANDARD);
        $this->createSchema($kernel);
        $importer = $this->importer($kernel);
        $first    = $importer->import(['menu' => ['code' => 'sidebar'], 'items' => [['label' => 'One'], ['label' => 'Two']]]);
        self::assertSame(1, $first['created']);
        $menuId = $this->menuId($kernel, 'sidebar');

        // A failure in the middle of the re-inserted tree (label longer than the column): the old tree stays.
        $caught = null;
        $result = null;
        try {
            $result = $importer->import(['menu' => ['code' => 'sidebar'], 'items' => [['label' => 'New'], ['label' => str_repeat('x', 300)]]], MenuImporter::STRATEGY_REPLACE);
        } catch (Throwable $failure) {
            $caught = $failure;
        }
        self::assertInstanceOf(DriverException::class, $caught, 'the over-long label fails the import (not a lock conflict): ' . json_encode($result));
        self::assertSame(['One', 'Two'], $this->labels($kernel, $menuId));
        $this->assertUsable($kernel);

        // Another unit holds the menu: the replace waits, fails as a lock conflict (CONFLICT_001), changes nothing.
        $holder = $this->connection($url);
        $holder->beginTransaction();
        $holder->fetchOne('SELECT id FROM dashboard_menu WHERE id = ? FOR UPDATE', [$menuId]);
        try {
            $importer->import(['menu' => ['code' => 'sidebar'], 'items' => [['label' => 'Three']]], MenuImporter::STRATEGY_REPLACE);
            self::fail('The held menu lock must stop the replace.');
        } catch (TransientStorageFailure $failure) {
            $answer = (new DashboardMenuConflictAnswer())->httpExceptionFor(new Request(), $failure);
            self::assertNotNull($answer);
            self::assertSame(409, $answer->getStatusCode());
            self::assertSame(ConflictCode::RETRY_SAFE, $answer->getHeaders()['X-Conflict-Code']);
        }
        $holder->rollBack();
        self::assertSame(['One', 'Two'], $this->labels($kernel, $menuId));

        $replaced = $importer->import(['menu' => ['code' => 'sidebar'], 'items' => [['label' => 'Three']]], MenuImporter::STRATEGY_REPLACE);
        self::assertSame(1, $replaced['updated']);
        self::assertSame(['Three'], $this->labels($kernel, $menuId));
    }

    #[DataProvider('databases')]
    public function testConcurrentCreateOfTheSameCodeIsRefusedWithoutHalfImport(string $env): void
    {
        $url    = $this->url($env);
        $kernel = $this->boot($url, DatabaseTestKernel::STANDARD);
        $this->createSchema($kernel);
        $other = $this->connection($url);
        $this->installConcurrentCreate($kernel, $other, 'topbar');

        // The second menu of the payload collides with a row another connection commits between check and insert.
        $result = $this->importer($kernel)->import(['menus' => [
            ['menu' => ['code' => 'sidebar'], 'items' => [['label' => 'One']]],
            ['menu' => ['code' => 'topbar'], 'items' => [['label' => 'Two']]],
        ]]);
        self::assertSame(['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [MenuImporter::CONCURRENT_CREATE_ERROR]], $result);
        self::assertSame(['topbar'], $this->codes($kernel));
        $this->assertUsable($kernel);
    }

    #[DataProvider('databases')]
    public function testConcurrentCreateFromTheDashboardAnswersTheFormError(string $env): void
    {
        $url    = $this->url($env);
        $kernel = $this->boot($url, DatabaseTestKernel::STANDARD);
        $this->createSchema($kernel);
        $other = $this->connection($url);
        $this->installConcurrentCreate($kernel, $other, 'sidebar');
        $client = $this->client($kernel);

        $client->request('GET', '/admin/menus/menu/new');
        $form = $client->getCrawler()->filterXPath('//form')->form();
        $form->setValues([$this->fieldEndingWith($form->getValues(), '[code]') => 'sidebar']);
        $client->submit($form);
        $response = $client->getResponse();
        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($response->headers->has('X-Conflict-Code'));
        self::assertSame(['sidebar'], $this->codes($kernel));
        $this->assertUsable($kernel);
    }

    private function assertConcurrentAppends(string $url, string $mode): void
    {
        $first  = $this->boot($url, $mode, 'a');
        $second = $this->boot($url, $mode, 'b');
        $this->createSchema($first);
        $menuId = $this->createMenu($first, 'sidebar');
        $client = $this->client($first);
        $fields = $this->newItemForm($client, $menuId);

        $services = $second->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $services);
        $transaction = $services->get(DashboardMenuTransaction::class);
        self::assertInstanceOf(DashboardMenuTransaction::class, $transaction);
        $repository = $this->manager($second)->getRepository(Menu::class);
        self::assertInstanceOf(MenuRepository::class, $repository);
        $items = $this->manager($second)->getRepository(MenuItem::class);
        self::assertInstanceOf(MenuItemRepository::class, $items);
        $transaction->run(static function (EntityManagerInterface $em) use ($client, $fields, $menuId, $repository, $items): void {
            $menu = $em->find(Menu::class, $menuId);
            self::assertInstanceOf(Menu::class, $menu);
            self::assertTrue($repository->lockForWrite($menu));
            $item = new MenuItem();
            $item->setMenu($menu);
            $item->setLabel('second');
            $item->setPosition($items->findMaxPositionForParent($menu, null) + 1);
            $em->persist($item);
            $em->flush();

            // The other connection appends while this unit holds the menu lock: it waits, then answers 409.
            $client->request('POST', '/admin/menus/' . $menuId . '/item/new', $fields);
            $response = $client->getResponse();
            self::assertSame(409, $response->getStatusCode());
            self::assertSame(ConflictCode::RETRY_SAFE, $response->headers->get('X-Conflict-Code'));
            self::assertSame('1', $response->headers->get('Retry-After'));
        });

        // The lock is released: the retry reads the committed position and appends after it.
        $client->request('POST', '/admin/menus/' . $menuId . '/item/new', $fields);
        self::assertSame(302, $client->getResponse()->getStatusCode());
        self::assertSame([0, 1], $this->positions($first, $menuId));
        $this->assertUsable($first);
    }

    /**
     * The new-item form of the dashboard, as the browser would post it.
     *
     * @return array<string, mixed>
     */
    private function newItemForm(KernelBrowser $client, int $menuId): array
    {
        $crawler = $client->request('GET', '/admin/menus/' . $menuId . '/item/new');
        self::assertSame(200, $client->getResponse()->getStatusCode(), substr(strip_tags((string) $client->getResponse()->getContent()), 0, 2000));
        $form   = $crawler->filterXPath('//form')->form();
        $values = $form->getPhpValues();
        $label  = $this->fieldEndingWith($form->getValues(), '[label]');
        $form->setValues([$label => 'first']);

        return $form->getPhpValues() + $values;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function fieldEndingWith(array $values, string $suffix): string
    {
        foreach ($values as $name => $value) {
            if (str_ends_with($name, $suffix)) {
                return $name;
            }
        }

        self::fail('No form field ends with ' . $suffix . ' (fields: ' . implode(', ', array_keys($values)) . ').');
    }

    /**
     * Commits a menu with $code on $other while the kernel's manager is about to insert it.
     */
    private function installConcurrentCreate(DatabaseTestKernel $kernel, Connection $other, string $code): void
    {
        $hook = new ConcurrentCreateHook(static function (Menu $menu) use ($other, $code): bool {
            if ($menu->getCode() !== $code) {
                return false;
            }
            // An explicit id: on PostgreSQL with DBAL 3 the id comes from a sequence the ORM reads, not a column default.
            $other->insert('dashboard_menu', ['id' => 900_000 + random_int(1, 99_999), 'code' => $code, 'attributes_key' => Menu::canonicalContextKey(null)]);

            return true;
        });
        $this->manager($kernel)->getEventManager()->addEventListener([Events::prePersist], $hook);
    }

    private function url(string $env): string
    {
        $url = getenv($env);
        if (!is_string($url) || $url === '') {
            self::markTestSkipped($env . ' is not set.');
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $conflictResponse
     */
    private function boot(string $url, string $mode, string $instance = 'a', bool $transactionDoctrine = false, array $conflictResponse = []): DatabaseTestKernel
    {
        $kernel = new DatabaseTestKernel($url, $mode, $instance, $transactionDoctrine, $conflictResponse);
        $kernel->boot();
        $this->kernels[] = $kernel;
        $registry        = $kernel->getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        foreach ($registry->getConnections() as $connection) {
            self::assertInstanceOf(Connection::class, $connection);
            $this->shortLockWait($connection);
        }

        return $kernel;
    }

    private function connection(string $url): Connection
    {
        $params              = (new DsnParser(['mysql' => 'pdo_mysql', 'pgsql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);
        $connection          = DriverManager::getConnection($params);
        $this->connections[] = $connection;
        $this->shortLockWait($connection);

        return $connection;
    }

    private function shortLockWait(Connection $connection): void
    {
        $connection->executeStatement($connection->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? "SET lock_timeout = '1s'"
            : 'SET SESSION innodb_lock_wait_timeout = 1');
    }

    private function manager(DatabaseTestKernel $kernel): EntityManagerInterface
    {
        $registry = $kernel->getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManagerForClass(Menu::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function importer(DatabaseTestKernel $kernel): MenuImporter
    {
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $importer = $container->get(MenuImporter::class);
        self::assertInstanceOf(MenuImporter::class, $importer);

        return $importer;
    }

    private function client(DatabaseTestKernel $kernel): KernelBrowser
    {
        $client = $kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        return $client;
    }

    private function createSchema(DatabaseTestKernel $kernel): void
    {
        $manager = $this->manager($kernel);
        $tool    = new SchemaTool($manager);
        $classes = [$manager->getClassMetadata(Menu::class), $manager->getClassMetadata(MenuItem::class)];
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    private function createMenu(DatabaseTestKernel $kernel, string $code): int
    {
        $manager = $this->manager($kernel);
        $menu    = new Menu();
        $menu->setCode($code);
        $manager->persist($menu);
        $manager->flush();
        $id = $menu->getId();
        self::assertNotNull($id);
        $manager->clear();

        return $id;
    }

    private function menuId(DatabaseTestKernel $kernel, string $code): int
    {
        return (int) $this->manager($kernel)->getConnection()->fetchOne('SELECT id FROM dashboard_menu WHERE code = ?', [$code]);
    }

    /**
     * @return list<int>
     */
    private function positions(DatabaseTestKernel $kernel, int $menuId): array
    {
        $positions = array_map(intval(...), $this->manager($kernel)->getConnection()->fetchFirstColumn('SELECT position FROM dashboard_menu_item WHERE menu_id = ?', [$menuId]));
        sort($positions);

        return $positions;
    }

    /**
     * @return list<string>
     */
    private function labels(DatabaseTestKernel $kernel, int $menuId): array
    {
        return array_map(strval(...), $this->manager($kernel)->getConnection()->fetchFirstColumn('SELECT label FROM dashboard_menu_item WHERE menu_id = ? ORDER BY position, id', [$menuId]));
    }

    /**
     * @return list<string>
     */
    private function codes(DatabaseTestKernel $kernel): array
    {
        return array_map(strval(...), $this->manager($kernel)->getConnection()->fetchFirstColumn('SELECT code FROM dashboard_menu ORDER BY code'));
    }

    private function assertUsable(DatabaseTestKernel $kernel): void
    {
        $manager = $this->manager($kernel);
        self::assertTrue($manager->isOpen());
        self::assertSame(0, $manager->getConnection()->getTransactionNestingLevel());
        self::assertSame(1, (int) $manager->getConnection()->fetchOne('SELECT 1'));
    }
}
