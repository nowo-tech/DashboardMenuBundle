<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Worker;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Tests\Integration\Worker\CallbackCommand;
use Nowo\DashboardMenuBundle\Worker\DashboardMenuOwnEntityManagerSubscriber;
use Nowo\DashboardMenuBundle\Worker\UnitOfWorkBoundaries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageSkipEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;

/**
 * Audit NX CR1–CR3 (tracker follow-up): per-worker message state and a stack of main requests, through a real
 * Messenger {@see Worker}, a real {@see HttpKernel} (`handle()` + `terminate()`, no services resetter) and a real
 * console {@see Application} run in-process, with {@see DashboardMenuOwnEntityManagerSubscriber} counting the clears.
 */
#[CoversClass(UnitOfWorkBoundaries::class)]
#[CoversClass(DashboardMenuOwnEntityManagerSubscriber::class)]
final class UnitOfWorkBoundariesNxTest extends TestCase
{
    private EventDispatcher $dispatcher;

    private UnitOfWorkBoundaries $boundaries;

    private HttpKernel $kernel;

    private int $clears = 0;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $requestStack     = new RequestStack();
        $this->boundaries = new UnitOfWorkBoundaries($requestStack);
        $manager          = $this->createStub(EntityManagerInterface::class);
        $manager->method('isOpen')->willReturn(true);
        $manager->method('clear')->willReturnCallback(function (): void {
            ++$this->clears;
        });
        $this->dispatcher->addSubscriber($this->boundaries);
        $this->dispatcher->addSubscriber(new DashboardMenuOwnEntityManagerSubscriber($this->boundaries, [$manager], $this->createStub(ManagerRegistry::class), 'own'));
        $this->kernel = new HttpKernel($this->dispatcher, new ControllerResolver(), $requestStack, new ArgumentResolver());
    }

    /**
     * CR1: a worker run in-process inside a message handler, then a main request in the same handler: neither clears
     * the outer message's unit, and the outer message is still in flight after the inner worker stopped.
     */
    #[Test]
    public function aWorkerInsideAHandlerNeverEndsTheOuterMessage(): void
    {
        $inside = null;
        $this->worker(1, function () use (&$inside): void {
            $before = $this->clears;
            $this->worker(1, static function (): void {
            });
            self::assertTrue($this->boundaries->isMessageInFlight(), 'the inner worker stopped, the outer message runs');
            $this->request(static function (): void {
            }, false);
            $inside = $this->clears - $before;
        });

        self::assertSame(0, $inside);
        self::assertSame(2, $this->clears, 'the outer message clears when received and when handled');
        self::assertFalse($this->boundaries->isMessageInFlight());
        $this->assertRequestClears();
    }

    /**
     * CR2: a main request handled in-process inside another main request does not clear the outer one.
     */
    #[Test]
    public function aNestedMainRequestNeverClearsTheOuterRequest(): void
    {
        $inside = null;
        $this->request(function () use (&$inside): void {
            $before = $this->clears;
            $this->request(static function (): void {
            }, false);
            self::assertSame(1, $this->boundaries->requestsInFlight(), 'the nested request popped itself only');
            $inside = $this->clears - $before;
        });

        self::assertSame(0, $inside);
        self::assertSame(2, $this->clears, 'the outer request clears at its start and its terminate');
        self::assertSame(0, $this->boundaries->requestsInFlight());
    }

    /**
     * CR3: a listener skips a message with `shouldHandle(false)`: the Worker reports neither handled nor failed, the
     * message ends anyway (final decision at PHP_INT_MIN, then the worker's WorkerRunningEvent).
     */
    #[Test]
    public function aSkippedMessageEndsWithoutAHandledEvent(): void
    {
        $inFlight = [];
        $running  = function () use (&$inFlight): void {
            $inFlight[] = $this->boundaries->isMessageInFlight();
        };
        $this->dispatcher->addListener(WorkerRunningEvent::class, $running);
        $received = 0;
        $handled  = [];
        // The in-memory transport delivers the skipped (never acknowledged) message again.
        $this->worker(1, function () use (&$handled): void {
            $handled[] = $this->boundaries->isMessageInFlight();
        }, static function (WorkerMessageReceivedEvent $event) use (&$received): void {
            if (++$received === 1) {
                $event->shouldHandle(false);
            }
        });
        $this->dispatcher->removeListener(WorkerRunningEvent::class, $running);

        self::assertSame([true], $handled, 'only the second delivery is handled');
        self::assertSame([false, false, false], $inFlight, 'after the skipped message, the handled one and the idle loop');
        $this->assertRequestClears();

        // CR3b: a skipped received event without any worker event (a loop that drives the Messenger events itself).
        $event = new WorkerMessageReceivedEvent(new Envelope(new stdClass()), 'memory');
        $event->shouldHandle(false);
        $this->dispatcher->dispatch($event);
        self::assertFalse($this->boundaries->isMessageInFlight());
        $this->assertRequestClears();
    }

    #[Test]
    public function aNestedCommandNeverClearsTheOuterCommand(): void
    {
        $application = $this->application();
        $inside      = null;
        $application->addCommand(new CallbackCommand('app:inner', static fn (): int => 0));
        $application->addCommand(new CallbackCommand('app:outer', function () use ($application, &$inside): int {
            $before = $this->clears;
            $application->run(new ArrayInput(['command' => 'app:inner']), new NullOutput());
            $inside = $this->clears - $before;

            return 0;
        }));

        self::assertSame(0, $application->run(new ArrayInput(['command' => 'app:outer']), new NullOutput()));
        self::assertSame(0, $inside);
        self::assertSame(2, $this->clears, 'the outer command clears at its start and its end');
        self::assertSame(0, $this->boundaries->runningCommands());
        $this->assertRequestClears();
    }

    /**
     * A message dispatched synchronously (no worker) inside a request belongs to the request: no clear in between.
     */
    #[Test]
    public function aSynchronousMessageInsideARequestBelongsToTheRequest(): void
    {
        $handled = 0;
        $bus     = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([stdClass::class => [static function () use (&$handled): void {
            ++$handled;
        }]]))]);
        $inside = null;
        $this->request(function () use ($bus, &$inside): void {
            $before = $this->clears;
            $bus->dispatch(new stdClass());
            $inside = $this->clears - $before;
        });

        self::assertSame(1, $handled);
        self::assertSame(0, $inside);
        self::assertSame(2, $this->clears);
    }

    /**
     * `messenger:consume`-like command: the worker's messages are the outermost units inside it; the command's end
     * leaves nothing in flight.
     */
    #[Test]
    public function aWorkerInsideACommandClearsAtEveryMessage(): void
    {
        $application = $this->application();
        $application->addCommand(new CallbackCommand('app:consume', function (): int {
            $this->worker(2, static function (): void {
            });

            return 0;
        }));

        self::assertSame(0, $application->run(new ArrayInput(['command' => 'app:consume']), new NullOutput()));
        self::assertSame(2 + 2 * 2, $this->clears, 'the command start / end and every message received / handled');
        self::assertFalse($this->boundaries->isMessageInFlight());
        $this->assertRequestClears();
    }

    /**
     * Two workers one after the other (the first one's stop never came: a WorkerRunningEvent listener threw out of
     * `Worker::run()`) do not change each other's state.
     */
    #[Test]
    public function twoSequentialWorkersKeepTheirOwnState(): void
    {
        $this->worker(1, static function (): void {
        });
        self::assertSame(2, $this->clears);

        $throw = static function (): never {
            throw new RuntimeException('listener failed');
        };
        $this->dispatcher->addListener(WorkerRunningEvent::class, $throw);
        try {
            $this->worker(1, static function (): void {
            });
            self::fail('the running listener throws');
        } catch (RuntimeException) {
        } finally {
            $this->dispatcher->removeListener(WorkerRunningEvent::class, $throw);
        }

        self::assertFalse($this->boundaries->isMessageInFlight());
        $this->clears = 0;
        $this->worker(1, function (): void {
            self::assertTrue($this->boundaries->isMessageInFlight());
        });
        self::assertSame(2, $this->clears, 'the second worker\'s message is the outermost unit');
        $this->assertRequestClears();
    }

    /**
     * Unbalanced events: a stop / running event of another worker and the finish of an unknown request inside a
     * message change nothing; a nested request whose end never came is popped with its outer request.
     */
    #[Test]
    public function unbalancedEventsNeverEndAnotherUnit(): void
    {
        $this->worker(1, function (): void {
            $stranger = new Worker([], $this->createStub(MessageBusInterface::class));
            $this->dispatcher->dispatch(new WorkerRunningEvent($stranger, false));
            $this->dispatcher->dispatch(new WorkerStoppedEvent($stranger));
            $this->dispatcher->dispatch(new stdClass(), UnitOfWorkBoundaries::MESSENGER_WORKER_STOPPED);
            self::assertTrue($this->boundaries->isMessageInFlight(), 'another worker\'s events do not end this message');
            $this->request(static function (): void {
            }, false);
        });
        self::assertFalse($this->boundaries->isMessageInFlight());

        $this->clears = 0;
        $this->request(function (): void {
            // A nested main request whose finish / terminate never came.
            $this->dispatcher->dispatch(new RequestEvent($this->kernel, Request::create('/lost'), HttpKernelInterface::MAIN_REQUEST), KernelEvents::REQUEST);
            self::assertSame(2, $this->boundaries->requestsInFlight());
        }, false);
        self::assertSame(0, $this->boundaries->requestsInFlight(), 'the outer finish pops the lost nested request');
        $this->assertRequestClears();
    }

    /**
     * Exceptions in the middle of a unit: a failing handler, and a received listener that throws out of an in-process
     * worker (inside a command: the command's end restores its state; outside: the next top-level terminate resets).
     */
    #[Test]
    public function anExceptionMidUnitNeverLeavesAMessageInFlight(): void
    {
        $this->worker(1, static function (): never {
            throw new RuntimeException('handler failed');
        });
        self::assertSame(2, $this->clears, 'received and failed');
        self::assertFalse($this->boundaries->isMessageInFlight());

        $throw = static function (): never {
            throw new RuntimeException('received listener failed');
        };
        $application = $this->application();
        $application->addCommand(new CallbackCommand('app:consume', function () use ($throw): int {
            $this->worker(1, static function (): void {
            }, $throw);

            return 0;
        }));
        self::assertNotSame(0, $application->run(new ArrayInput(['command' => 'app:consume']), new NullOutput()));
        self::assertFalse($this->boundaries->isMessageInFlight(), 'the command frame restored the state of its start');
        $this->assertRequestClears();

        try {
            $this->worker(1, static function (): void {
            }, $throw);
            self::fail('the received listener throws');
        } catch (RuntimeException) {
        }
        self::assertFalse($this->boundaries->isMessageInFlight(), 'the aborted run is gone with its worker (audit OB-M1)');
        $this->assertRequestClears();
    }

    /**
     * Audit NR-b with the request stack: a top-level request whose `kernel.exception` listener throws never reaches its
     * finish_request / terminate; the next top-level request is still the outermost unit from its start.
     */
    #[Test]
    public function aRequestWhoseEndNeverCameIsDroppedAtTheNextTopLevelStart(): void
    {
        $throw = static function (): never {
            throw new RuntimeException('exception listener failed');
        };
        $this->dispatcher->addListener(KernelEvents::EXCEPTION, $throw);
        try {
            $this->request(static function (): never {
                throw new RuntimeException('controller failed');
            }, true, true);
            self::fail('the kernel.exception listener throws');
        } catch (RuntimeException) {
        } finally {
            $this->dispatcher->removeListener(KernelEvents::EXCEPTION, $throw);
        }

        self::assertSame(1, $this->boundaries->requestsInFlight());
        $this->assertRequestClears();
        self::assertSame(0, $this->boundaries->requestsInFlight());
    }

    /**
     * Audit OB-M1 (S8): a long-running command runs workers in a loop and catches their exceptions; the first run aborts
     * in a throwing received listener (no handled, running or stopped event). The later workers' messages are still the
     * outermost units.
     */
    #[Test]
    public function anAbortedWorkerInsideALongCommandNeverStopsTheNextMessagesFromClearing(): void
    {
        $application = $this->application();
        $perWorker   = [];
        $application->addCommand(new CallbackCommand('app:loop', function () use (&$perWorker): int {
            $throw = static function (): never {
                throw new RuntimeException('received listener failed');
            };
            $this->dispatcher->addListener(WorkerMessageReceivedEvent::class, $throw);
            try {
                $this->worker(1, static function (): void {
                });
            } catch (RuntimeException) {
            } finally {
                $this->dispatcher->removeListener(WorkerMessageReceivedEvent::class, $throw);
            }

            for ($i = 0; $i < 3; ++$i) {
                $before = $this->clears;
                $this->worker(2, static function (): void {
                });
                $perWorker[] = $this->clears - $before;
            }

            return 0;
        }));

        self::assertSame(0, $application->run(new ArrayInput(['command' => 'app:loop']), new NullOutput()));
        self::assertSame([4, 4, 4], $perWorker, 'every message clears when received and when handled');
        $this->assertRequestClears();
    }

    /**
     * Audit OB-L1 (S13): 500 runs aborted by a throwing handled listener inside one command leave a bounded stack.
     */
    #[Test]
    public function abortedRunsNeverGrowTheWorkerStack(): void
    {
        $application = $this->application();
        $entries     = null;
        $clears      = null;
        $application->addCommand(new CallbackCommand('app:loop', function () use (&$entries, &$clears): int {
            $throw = static function (): never {
                throw new RuntimeException('handled listener failed');
            };
            $this->dispatcher->addListener(WorkerMessageHandledEvent::class, $throw);
            for ($i = 0; $i < 500; ++$i) {
                try {
                    $this->worker(1, static function (): void {
                    });
                } catch (RuntimeException) {
                }
            }
            $this->dispatcher->removeListener(WorkerMessageHandledEvent::class, $throw);
            gc_collect_cycles();
            $entries = $this->boundaries->workerEntries();
            $before  = $this->clears;
            $this->worker(2, static function (): void {
            });
            $clears = $this->clears - $before;

            return 0;
        }));

        self::assertSame(0, $application->run(new ArrayInput(['command' => 'app:loop']), new NullOutput()));
        self::assertSame(0, $entries, 'no aborted run is kept');
        self::assertSame(4, $clears);
    }

    /**
     * Audit OB-L2 (S9): a received listener of an inner worker dispatches `WorkerMessageSkipEvent` (as
     * `messenger:failed:retry` does) and skips the message: only the inner message ends, the outer one still runs.
     */
    #[Test]
    public function aSkipEventFromAReceivedListenerNeverEndsTheOuterMessage(): void
    {
        $inside = null;
        $this->worker(1, function () use (&$inside): void {
            $skip = function (WorkerMessageReceivedEvent $event): void {
                $this->dispatcher->dispatch(new WorkerMessageSkipEvent($event->getEnvelope(), $event->getReceiverName()));
                $event->shouldHandle(false);
            };
            $this->dispatcher->addListener(WorkerMessageReceivedEvent::class, $skip);
            $this->worker(1, static function (): void {
            }, null, true);
            $this->dispatcher->removeListener(WorkerMessageReceivedEvent::class, $skip);
            self::assertTrue($this->boundaries->isMessageInFlight(), 'the outer message still runs');
            $before = $this->clears;
            $this->request(static function (): void {
            });
            $inside = $this->clears - $before;
        });

        self::assertSame(0, $inside);
        $this->assertRequestClears();
    }

    /**
     * Audit OB-L3 (S10): the in-process `kernel.terminate` of a nested main request does not clear the outer request.
     */
    #[Test]
    public function theTerminateOfANestedMainRequestKeepsTheOuterRequest(): void
    {
        $inside = null;
        $this->request(function () use (&$inside): void {
            $before = $this->clears;
            $this->request(static function (): void {
            });
            $inside = $this->clears - $before;
            self::assertSame(1, $this->boundaries->requestsInFlight());
        });

        self::assertSame(0, $inside);
        self::assertSame(2, $this->clears);
        $this->assertRequestClears();
    }

    private function assertRequestClears(): void
    {
        $before = $this->clears;
        $this->request(static function (): void {
        });
        self::assertSame(2, $this->clears - $before, 'a top-level request clears at its start and its terminate');
    }

    private function request(Closure $body, bool $terminate = true, bool $catch = false): void
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', static function () use ($body): Response {
            $body();

            return new Response('ok');
        });
        $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, $catch);
        if ($terminate) {
            $this->kernel->terminate($request, $response);
        }
    }

    /**
     * Runs a real Messenger worker over $count messages until it is idle.
     */
    private function worker(int $count, Closure $handler, ?Closure $onReceived = null, bool $stopAfterFirst = false): void
    {
        $transport = new InMemoryTransport();
        for ($i = 0; $i < $count; ++$i) {
            $transport->send(new Envelope(new stdClass()));
        }

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use ($handler): Envelope {
            $handler();

            return $message instanceof Envelope ? $message : new Envelope($message);
        });
        $worker = new Worker(['memory' => $transport], $bus, $this->dispatcher);
        $stop   = static function (WorkerRunningEvent $event) use ($worker, $stopAfterFirst): void {
            if ($event->getWorker() === $worker && ($stopAfterFirst || $event->isWorkerIdle())) {
                $worker->stop();
            }
        };
        $this->dispatcher->addListener(WorkerRunningEvent::class, $stop, -1);
        if ($onReceived instanceof Closure) {
            $this->dispatcher->addListener(WorkerMessageReceivedEvent::class, $onReceived);
        }

        try {
            $worker->run(['sleep' => 0]);
        } finally {
            $this->dispatcher->removeListener(WorkerRunningEvent::class, $stop);
            if ($onReceived instanceof Closure) {
                $this->dispatcher->removeListener(WorkerMessageReceivedEvent::class, $onReceived);
            }
        }
    }

    private function application(): Application
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(true);
        $application->setDispatcher($this->dispatcher);

        return $application;
    }
}
