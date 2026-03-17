<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DataCollector;

use Doctrine\DBAL\Configuration;

/**
 * Stub to satisfy Connection::getConfiguration() return type and allow testing
 * MenuQueryCounter::wrapConnection() with configs that have getSQLLogger/setSQLLogger.
 *
 * @internal
 */
final class ConfigurationStub extends Configuration
{
    public ?object $logger = null;

    public int $setCalls = 0;

    public function getSQLLogger(): ?object
    {
        return $this->logger;
    }

    public function setSQLLogger(?object $logger): void
    {
        ++$this->setCalls;
        $this->logger = $logger;
    }
}
