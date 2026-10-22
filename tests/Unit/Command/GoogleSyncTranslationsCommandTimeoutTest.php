<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Command;

use Nowo\DashboardMenuBundle\Command\GoogleSyncTranslationsCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * REQ-RUNTIME-001: a translate endpoint that never answers must not hang the command.
 */
final class GoogleSyncTranslationsCommandTimeoutTest extends TestCase
{
    public function testUnresponsiveEndpointTimesOutAndKeepsTheSourceText(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            self::markTestSkipped('Cannot open a local TCP socket: ' . $errstr);
        }
        $address = stream_socket_get_name($server, false);
        self::assertIsString($address);

        $command = new GoogleSyncTranslationsCommand('http://' . $address . '/translate?key=%s');
        $method  = new ReflectionMethod($command, 'translateWithGoogle');

        $started = microtime(true);
        $result  = $method->invoke($command, 'key', 'Hello', 'en', 'es', 0.5);
        fclose($server);

        self::assertSame('Hello', $result);
        self::assertLessThan(5.0, microtime(true) - $started);
    }

    public function testTimeoutOptionDefaultsToTwentySeconds(): void
    {
        $option = (new GoogleSyncTranslationsCommand())->getDefinition()->getOption('timeout');

        self::assertSame('20', $option->getDefault());
    }
}
