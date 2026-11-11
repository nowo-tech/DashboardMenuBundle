<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\DashboardMenuBundle\Entity\Menu;

use function array_keys;

/**
 * The EntityManager of the menu entities (2.18, architecture bar rule 6): `doctrine.entity_manager` when set, else
 * the manager that maps {@see Menu} (the default one in a standard host). Never the host default by accident, and only
 * that manager is ever reset. Stateless (worker-safe): the manager is looked up per call.
 */
final readonly class DashboardMenuEntityManagerResolver
{
    public function __construct(
        private ManagerRegistry $registry,
        private ?string $managerName = null,
    ) {
    }

    public function entityManager(): EntityManagerInterface
    {
        $manager = $this->find();
        if (!$manager instanceof EntityManagerInterface) {
            throw new LogicException('No Doctrine ORM entity manager maps ' . Menu::class . ' (nowo_dashboard_menu.doctrine.entity_manager: ' . ($this->managerName ?? 'null') . ').');
        }

        return $manager;
    }

    /**
     * Resets the menu manager when a failed flush closed it; never another manager.
     */
    public function resetIfClosed(): void
    {
        $manager = $this->find();
        if (!$manager instanceof EntityManagerInterface || $manager->isOpen()) {
            return;
        }

        if ($this->managerName !== null) {
            $this->registry->resetManager($this->managerName);

            return;
        }

        foreach (array_keys($this->registry->getManagerNames()) as $name) {
            if ($this->registry->getManager($name) === $manager) {
                $this->registry->resetManager($name);

                return;
            }
        }
    }

    private function find(): ?EntityManagerInterface
    {
        $manager = $this->managerName !== null
            ? $this->registry->getManager($this->managerName)
            : $this->registry->getManagerForClass(Menu::class);

        return $manager instanceof EntityManagerInterface ? $manager : null;
    }
}
