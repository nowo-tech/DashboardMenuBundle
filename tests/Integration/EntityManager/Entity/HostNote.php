<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\EntityManager\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A host entity on the host's manager (dedicated entity manager tests): never flushed, cleared or reset by the bundle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'host_note')]
class HostNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (Doctrine UnitOfWork)

    public function __construct(
        #[ORM\Column(length: 64)]
        public string $text,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
