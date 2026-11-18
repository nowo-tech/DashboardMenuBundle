<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Database;

use Closure;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Nowo\DashboardMenuBundle\Entity\Menu;

/**
 * Test-only prePersist listener: lets another connection commit a conflicting menu between the bundle's "same code
 * and context" check and its insert (the race the unique key and its catch close). Fires once.
 */
final class ConcurrentCreateHook
{
    private bool $fired = false;

    /**
     * @param Closure(Menu): bool $callback returns true once it has committed the conflicting menu
     */
    public function __construct(private readonly Closure $callback)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->fired && $entity instanceof Menu) {
            $this->fired = ($this->callback)($entity);
        }
    }
}
