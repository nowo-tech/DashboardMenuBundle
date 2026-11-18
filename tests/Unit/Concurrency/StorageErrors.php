<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Concurrency;

use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Storage errors as the DBAL drivers raise them (vendor code + SQLSTATE), for the conflict-mapping tests.
 */
final class StorageErrors
{
    public static function deadlock(): DeadlockException
    {
        return new DeadlockException(self::driver('Deadlock found', '40001', 1213), null);
    }

    public static function lockWaitTimeout(): LockWaitTimeoutException
    {
        return new LockWaitTimeoutException(self::driver('Lock wait timeout exceeded', 'HY000', 1205), null);
    }

    public static function postgresLockNotAvailable(): LockWaitTimeoutException
    {
        return new LockWaitTimeoutException(self::driver('canceling statement due to lock timeout', '55P03', 7), null);
    }

    public static function serializationFailure(): DriverException
    {
        return new DriverException(self::driver('could not serialize access', '40001', 7), null);
    }

    public static function lostSavepoint(): DriverException
    {
        return new DriverException(self::driver('SAVEPOINT DOCTRINE_2 does not exist', '42000', 1305), null);
    }

    public static function uniqueViolation(): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException(self::driver('Duplicate entry', '23000', 1062), null);
    }

    public static function connectionLost(): ConnectionLost
    {
        return new ConnectionLost(self::driver('MySQL server has gone away', 'HY000', 2006), null);
    }

    private static function driver(string $message, string $sqlState, int $code): AbstractException
    {
        return new class($message, $sqlState, $code) extends AbstractException {
        };
    }
}
