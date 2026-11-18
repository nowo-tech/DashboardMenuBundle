<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Database;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Test host listener: with the header `X-Test-Commit-First`, the host commits a write before the bundle's controller
 * runs (after transaction-doctrine's request watermark), so a later conflict of the same request is CONFLICT_002.
 */
final readonly class EarlierCommitListener
{
    public const HEADER = 'X-Test-Commit-First';

    public function __construct(private Connection $connection)
    {
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest() && $event->getRequest()->headers->has(self::HEADER)) {
            $this->connection->executeStatement('UPDATE dashboard_menu SET name = name WHERE id = 0');
        }
    }
}
