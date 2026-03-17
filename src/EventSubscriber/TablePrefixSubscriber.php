<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;

/**
 * Applies configurable table prefix to menu entities at runtime.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsDoctrineListener(event: Events::loadClassMetadata)]
final readonly class TablePrefixSubscriber
{
    public function __construct(
        private string $tablePrefix,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();
        $class    = $metadata->getName();

        if ($this->tablePrefix === '' || ($class !== Menu::class && $class !== MenuItem::class)) {
            return;
        }

        $metadata->setPrimaryTable([
            'name' => $this->tablePrefix . $metadata->getTableName(),
        ]);
    }
}
