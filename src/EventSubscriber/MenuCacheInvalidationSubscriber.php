<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuTreeCacheInvalidator;

use function is_string;

/**
 * Bumps the menu tree cache version when menus or items are persisted, updated, or removed.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final readonly class MenuCacheInvalidationSubscriber
{
    public function __construct(
        private MenuTreeCacheInvalidator $cacheInvalidator,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateForEntity($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Menu) {
            $this->cacheInvalidator->invalidateForMenuCode($entity->getCode());
            $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);
            if (isset($changeSet['code'][0]) && is_string($changeSet['code'][0]) && $changeSet['code'][0] !== '') {
                $this->cacheInvalidator->invalidateForMenuCode($changeSet['code'][0]);
            }

            return;
        }

        $this->invalidateForEntity($entity);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateForEntity($args->getObject());
    }

    private function invalidateForEntity(object $entity): void
    {
        if ($entity instanceof Menu) {
            $this->cacheInvalidator->invalidateForMenuCode($entity->getCode());

            return;
        }

        if ($entity instanceof MenuItem) {
            $menu = $entity->getMenu();
            if ($menu instanceof Menu) {
                $this->cacheInvalidator->invalidateForMenuCode($menu->getCode());
            }
        }
    }
}
