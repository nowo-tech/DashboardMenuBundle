<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use Nowo\DashboardMenuBundle\Tests\Integration\EntityManager\Entity\HostNote;
use Nowo\DashboardMenuBundle\Worker\DashboardMenuOwnEntityManagerSubscriber;
use Nowo\DashboardMenuBundle\Worker\UnitOfWorkBoundaries;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use function array_filter;
use function dirname;
use function gc_collect_cycles;
use function getmypid;
use function json_decode;
use function memory_get_usage;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * REQ-RUNTIME-002 (2.21.0) through ONE kept-alive kernel (FrankenPHP worker mode, kernel reset false): the
 * `http_kernel` service is driven directly and the services resetter never runs.
 *
 * - two users alternate over N requests and see only their own items, the bundle's own manager holds no entity after
 *   each request, the host's manager keeps its pending change, memory stays flat;
 * - the host's default manager is never cleared (standard host and `doctrine.entity_manager` = the default name);
 * - probes: throwing console / Messenger / kernel listeners never leave the process "inside a unit" (the next request
 *   still clears), and nested units never clear the outer unit's manager.
 */
final class WorkerKeptAliveKernelTest extends TestCase
{
    private const ITERATIONS = 1000;

    private const MEMORY_MARGIN = 4 * 1024 * 1024;

    private ?WorkerTestKernel $kernel = null;

    private string $directory = '';

    /** Menu name of the last {@see Menu()} response. */
    private ?string $lastName = null;

    protected function tearDown(): void
    {
        $directories = [$this->directory];
        if ($this->kernel instanceof WorkerTestKernel) {
            // The compiled container of this boot (one cache directory per database directory).
            $directories[] = dirname($this->kernel->getCacheDir());
            $this->kernel->shutdown();
        }
        $this->kernel = null;
        (new Filesystem())->remove(array_filter($directories, static fn (string $directory): bool => $directory !== ''));
    }

    public function testTwoUsersAlternateOverManyRequestsWithIsolationAndFlatMemory(): void
    {
        $container = $this->boot(WorkerTestKernel::DEDICATED);
        $bundle    = $this->manager($container, 'dashboard_menu');
        $host      = $this->manager($container, 'default');
        $note      = new HostNote('pending host change');
        $host->persist($note);
        $kernel = $this->httpKernel($container);

        $expected   = ['alice' => ['Home', 'Reports'], 'bob' => ['Home', 'Billing']];
        $connection = $bundle->getConnection();
        $table      = $bundle->getClassMetadata(Menu::class)->getTableName();
        $baseline   = 0;
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $user = $i % 2 === 0 ? 'alice' : 'bob';
            // Changed behind Doctrine's back: a manager or memo kept across requests would serve the old name.
            $connection->update($table, ['name' => 'Sidebar ' . $i], ['code' => 'sidebar']);
            self::assertSame($expected[$user], $this->menu($kernel, $user), 'request ' . $i . ' shows only ' . $user . '\'s items');
            self::assertSame('Sidebar ' . $i, $this->lastName, 'request ' . $i . ' reads the fresh menu');
            self::assertSame(0, $bundle->getUnitOfWork()->size(), 'no menu entity survives request ' . $i);
            if ($i === 50) {
                $baseline = $this->collectedMemory();
            }
        }

        self::assertLessThan(self::MEMORY_MARGIN, $this->collectedMemory() - $baseline, 'memory stays flat over many requests');
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($note), 'the host manager is never cleared');
    }

    public function testClosedOwnManagerIsRecreatedAtTheNextBoundary(): void
    {
        $container = $this->boot(WorkerTestKernel::DEDICATED);
        $bundle    = $this->manager($container, 'dashboard_menu');
        $bundle->close();

        self::assertSame(['Home', 'Reports'], $this->menu($this->httpKernel($container), 'alice'));
        self::assertTrue($this->manager($container, 'dashboard_menu')->isOpen());
    }

    public function testStandardHostManagerIsNeverClearedByTheBundle(): void
    {
        $container = $this->boot(WorkerTestKernel::STANDARD);
        self::assertFalse($container->has(DashboardMenuOwnEntityManagerSubscriber::class));
        $default = $this->manager($container, 'default');

        self::assertSame(['Home', 'Billing'], $this->menu($this->httpKernel($container), 'bob'));
        self::assertTrue($default->getUnitOfWork()->size() > 0, 'the host default manager keeps its entities (unchanged default)');
    }

    public function testDedicatedOptionNamingTheHostDefaultNeverClearsIt(): void
    {
        $container = $this->boot(WorkerTestKernel::DEFAULT_NAMED);
        self::assertFalse($container->has(DashboardMenuOwnEntityManagerSubscriber::class), 'rule 7: the host clears its own default manager');

        self::assertSame(['Home', 'Reports'], $this->menu($this->httpKernel($container), 'alice'));
        self::assertTrue($this->manager($container, 'default')->getUnitOfWork()->size() > 0);
    }

    /**
     * Outermost command and Messenger message boundaries clear the own manager; a command run inside a message, a
     * nested command and a request handled inside a command keep the outer unit's entities.
     */
    public function testNestedUnitsKeepTheOuterManagerAndOutermostUnitsClear(): void
    {
        $container  = $this->boot(WorkerTestKernel::DEDICATED);
        $bundle     = $this->manager($container, 'dashboard_menu');
        $dispatcher = $this->dispatcher($container);
        $kernel     = $this->httpKernel($container);

        $application = $this->application();
        $application->addCommand(new CallbackCommand('app:nested', static fn (): int => 0));
        $application->addCommand(new CallbackCommand('app:outer', function () use ($bundle, $application, $kernel): int {
            $this->loadMenu($bundle);
            self::assertSame(0, $application->run($this->input('app:nested'), new NullOutput()));
            self::assertSame(['Home', 'Reports'], $this->menu($kernel, 'alice'));
            self::assertGreaterThan(0, $bundle->getUnitOfWork()->size(), 'nested command and in-process request keep the outer entities');

            return 0;
        }));
        self::assertSame(0, $application->run($this->input('app:outer'), new NullOutput()));
        self::assertSame(0, $bundle->getUnitOfWork()->size(), 'the outermost command clears at terminate');

        $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED);
        $this->loadMenu($bundle);
        self::assertSame(0, $application->run($this->input('app:nested'), new NullOutput()));
        self::assertGreaterThan(0, $bundle->getUnitOfWork()->size(), 'a command run from the handler keeps the message entities');
        $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_HANDLED);
        self::assertSame(0, $bundle->getUnitOfWork()->size(), 'the message boundary clears');
    }

    public function testThrowingTerminateAndErrorListenersNeverStopTheClear(): void
    {
        [$container, $bundle, $dispatcher] = $this->dedicated();
        $throw                             = static function (): never {
            throw new RuntimeException('listener failed');
        };
        $application = $this->application();
        $application->addCommand(new CallbackCommand('app:ok', static fn (): int => 0));
        $application->addCommand(new CallbackCommand('app:fail', static function (): never {
            throw new RuntimeException('command failed');
        }));

        foreach ([[ConsoleEvents::TERMINATE, 'app:ok', 0], [ConsoleEvents::ERROR, 'app:fail', 0], [ConsoleEvents::ERROR, 'app:fail', PHP_INT_MIN], [ConsoleEvents::TERMINATE, 'app:ok', PHP_INT_MAX]] as [$eventName, $commandName, $priority]) {
            $dispatcher->addListener($eventName, $throw, $priority);
            self::assertNotSame(0, $application->run($this->input($commandName), new NullOutput()));
            $dispatcher->removeListener($eventName, $throw);

            $this->assertRequestClears($container, $bundle, 'after a throwing ' . $eventName . ' listener (priority ' . $priority . ')');
        }
    }

    public function testSelfRecursiveAndNestedFailingCommandsWithThrowingErrorListener(): void
    {
        [$container, $bundle, $dispatcher] = $this->dedicated();
        $throw                             = static function (): never {
            throw new RuntimeException('listener failed');
        };
        $application = $this->application();
        $depth       = 0;
        $application->addCommand(new CallbackCommand('app:rec', function () use ($application, &$depth, $dispatcher, $throw): int {
            ++$depth;
            if ($depth === 1) {
                $dispatcher->addListener(ConsoleEvents::ERROR, $throw);
                $application->run($this->input('app:rec'), new NullOutput());
                $dispatcher->removeListener(ConsoleEvents::ERROR, $throw);

                return 0;
            }

            throw new RuntimeException('inner failed');
        }));
        $application->addCommand(new CallbackCommand('app:inner', static function (): never {
            throw new RuntimeException('inner failed');
        }));
        $application->addCommand(new CallbackCommand('app:outer', function () use ($application, $dispatcher, $throw): int {
            $dispatcher->addListener(ConsoleEvents::ERROR, $throw);
            $application->run($this->input('app:inner'), new NullOutput());
            $dispatcher->removeListener(ConsoleEvents::ERROR, $throw);

            return 0;
        }));

        self::assertSame(0, $application->run($this->input('app:rec'), new NullOutput()));
        $this->assertRequestClears($container, $bundle, 'self-recursive command + throwing error listener');
        self::assertSame(0, $application->run($this->input('app:outer'), new NullOutput()));
        $this->assertRequestClears($container, $bundle, 'nested different command + throwing error listener');
    }

    public function testUnknownNestedCommandNeverClearsTheOuterUnit(): void
    {
        [, $bundle]  = $this->dedicated();
        $application = $this->application();
        $application->addCommand(new CallbackCommand('app:outer', function () use ($bundle): int {
            $this->loadMenu($bundle);
            self::assertNotSame(0, $this->application()->run($this->input('zzz:no-such-command'), new NullOutput()));
            self::assertGreaterThan(0, $bundle->getUnitOfWork()->size(), 'an unknown nested command does not clear the outer command');

            return 0;
        }));

        self::assertSame(0, $application->run($this->input('app:outer'), new NullOutput()));
    }

    public function testThrowingMessageReceivedListenerInsideConsumeHeals(): void
    {
        [$container, $bundle, $dispatcher] = $this->dedicated();
        $throw                             = static function (): never {
            throw new RuntimeException('received listener failed');
        };
        $application = $this->application();
        $application->addCommand(new CallbackCommand('app:consume', static function () use ($dispatcher, $throw): int {
            $dispatcher->addListener(UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED, $throw);
            try {
                $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED);
            } finally {
                $dispatcher->removeListener(UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED, $throw);
            }

            return 0;
        }));

        self::assertNotSame(0, $application->run($this->input('app:consume'), new NullOutput()));
        $this->assertRequestClears($container, $bundle, 'received listener threw inside consume (no WorkerStopped)');
    }

    /**
     * Revision 3: message received → the handler runs a failing command whose error listener throws → message failed
     * → worker stopped → the next request still clears.
     */
    public function testFailedCommandInsideAFailedMessageThenWorkerStopped(): void
    {
        [$container, $bundle, $dispatcher] = $this->dedicated();
        $throw                             = static function (): never {
            throw new RuntimeException('listener failed');
        };
        $application = $this->application();
        $application->addCommand(new CallbackCommand('app:fail', static function (): never {
            throw new RuntimeException('command failed');
        }));

        $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED);
        $dispatcher->addListener(ConsoleEvents::ERROR, $throw);
        $application->run($this->input('app:fail'), new NullOutput());
        $dispatcher->removeListener(ConsoleEvents::ERROR, $throw);
        $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_FAILED);
        $dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_STOPPED);

        $this->assertRequestClears($container, $bundle, 'failed command inside a failed message, then worker stopped');
    }

    /**
     * Revision 4: a throwing `kernel.exception` listener (no `kernel.finish_request`) does not leave a main request in
     * flight: after the next top-level request a command is the outermost unit again and clears.
     */
    public function testThrowingKernelExceptionListenerDoesNotLeaveARequestInFlight(): void
    {
        [$container, $bundle, $dispatcher] = $this->dedicated();
        $throw                             = static function (): never {
            throw new RuntimeException('exception listener failed');
        };
        $kernel = $this->httpKernel($container);
        $dispatcher->addListener(KernelEvents::EXCEPTION, $throw, PHP_INT_MAX);
        try {
            $kernel->handle(Request::create('/w/boom'), HttpKernelInterface::MAIN_REQUEST, true);
            self::fail('the exception listener throws');
        } catch (RuntimeException $exception) {
            self::assertSame('exception listener failed', $exception->getMessage());
        } finally {
            $dispatcher->removeListener(KernelEvents::EXCEPTION, $throw);
        }

        $this->assertRequestClears($container, $bundle, 'normal request after a throwing kernel.exception listener');
        $this->loadMenu($bundle);
        $application = $this->application();
        $application->addCommand(new CallbackCommand('app:check', static function () use ($bundle): int {
            self::assertSame(0, $bundle->getUnitOfWork()->size(), 'the command is the outermost unit and clears at start');

            return 0;
        }));
        self::assertSame(0, $application->run($this->input('app:check'), new NullOutput()));
    }

    /**
     * @return array{0: ContainerInterface, 1: EntityManagerInterface, 2: EventDispatcherInterface}
     */
    private function dedicated(): array
    {
        $container = $this->boot(WorkerTestKernel::DEDICATED);

        return [$container, $this->manager($container, 'dashboard_menu'), $this->dispatcher($container)];
    }

    private function assertRequestClears(ContainerInterface $container, EntityManagerInterface $bundle, string $label): void
    {
        $this->loadMenu($bundle);
        self::assertSame(['Home', 'Reports'], $this->menu($this->httpKernel($container), 'alice'), $label);
        self::assertSame(0, $bundle->getUnitOfWork()->size(), $label);
    }

    private function loadMenu(EntityManagerInterface $bundle): void
    {
        self::assertInstanceOf(Menu::class, $bundle->getRepository(Menu::class)->findOneBy(['code' => 'sidebar']));
    }

    /**
     * One full request (handle + terminate) for $user; returns the visible labels.
     *
     * @return list<string>
     */
    private function menu(HttpKernel $kernel, string $user): array
    {
        $request = Request::create('/w/menu');
        $request->headers->set('X-User', $user);
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $kernel->terminate($request, $response);

        /** @var array{name: string|null, items: list<string>} $body */
        $body           = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->lastName = $body['name'];

        return $body['items'];
    }

    private function boot(string $mode): ContainerInterface
    {
        $this->directory = sys_get_temp_dir() . '/nowo-dashboard-menu-worker-db/' . getmypid() . '/' . uniqid('', true);
        (new Filesystem())->mkdir($this->directory);
        $this->kernel = new WorkerTestKernel($mode, $this->directory);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        foreach ($registry->getManagers() as $manager) {
            self::assertInstanceOf(EntityManagerInterface::class, $manager);
            (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
        }

        $importer = $container->get(MenuImporter::class);
        self::assertInstanceOf(MenuImporter::class, $importer);
        $importer->import(['menu' => ['code' => 'sidebar', 'name' => 'Sidebar', 'permissionChecker' => WorkerUserChecker::class], 'items' => [
            ['label' => 'Home', 'position' => 0, 'linkType' => 'external', 'externalUrl' => '/'],
            ['label' => 'Reports', 'position' => 1, 'linkType' => 'external', 'externalUrl' => '/reports', 'permissionKeys' => ['reports']],
            ['label' => 'Billing', 'position' => 2, 'linkType' => 'external', 'externalUrl' => '/billing', 'permissionKeys' => ['billing']],
        ]]);
        $manager = $container->get('nowo_dashboard_menu.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        $manager->clear();

        return $container;
    }

    private function application(): Application
    {
        self::assertNotNull($this->kernel);
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(true);

        return $application;
    }

    private function input(string $command): ArrayInput
    {
        $input = new ArrayInput(['command' => $command]);
        $input->setInteractive(false);

        return $input;
    }

    private function manager(ContainerInterface $container, string $name): EntityManagerInterface
    {
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManager($name);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function dispatcher(ContainerInterface $container): EventDispatcherInterface
    {
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        return $dispatcher;
    }

    private function httpKernel(ContainerInterface $container): HttpKernel
    {
        $kernel = $container->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $kernel);

        return $kernel;
    }

    /**
     * Memory after a cycle collection (request / event objects form cycles freed only when the collector runs).
     */
    private function collectedMemory(): int
    {
        gc_collect_cycles();

        return memory_get_usage();
    }
}
