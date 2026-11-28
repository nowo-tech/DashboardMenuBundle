<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Support;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function restore_error_handler;
use function set_error_handler;
use function str_contains;

use const E_USER_DEPRECATED;

/**
 * Adds or removes a probe listener on a booted kernel's dispatcher.
 *
 * Symfony 8.2 deprecates `addListener()` / `removeListener()` on the compiled dispatcher (listeners belong in the
 * container or in a `ScopedEventDispatcher`). These tests observe the kernel's own dispatcher on purpose (the code under
 * test dispatches on it), so the 8.2 notice for these calls is expected and stays out of the deprecation report.
 */
final class KernelListeners
{
    public static function add(EventDispatcherInterface $dispatcher, string $eventName, callable $listener, int $priority = 0): void
    {
        self::quietly(static function () use ($dispatcher, $eventName, $listener, $priority): void {
            $dispatcher->addListener($eventName, $listener, $priority);
        });
    }

    public static function remove(EventDispatcherInterface $dispatcher, string $eventName, callable $listener): void
    {
        self::quietly(static function () use ($dispatcher, $eventName, $listener): void {
            $dispatcher->removeListener($eventName, $listener);
        });
    }

    private static function quietly(callable $call): void
    {
        set_error_handler(static fn (int $level, string $message): bool => str_contains($message, 'ScopedEventDispatcher'), E_USER_DEPRECATED); // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test helper; restored in finally)

        try {
            $call();
        } finally {
            restore_error_handler();
        }
    }
}
