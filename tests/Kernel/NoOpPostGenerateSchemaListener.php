<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Kernel;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Replaces Symfony's cache-adapter schema listener in the sqlite test kernel.
 *
 * ORM 3.6.8 calls {@see GenerateSchemaEventArgs::setSchema()} which requires
 * DBAL {@code Schema::edit()} (4.5-dev). Tests do not use a Doctrine DBAL cache adapter.
 */
final class NoOpPostGenerateSchemaListener
{
    /**
     * @param mixed ...$args Original listener constructor args (unused)
     */
    public function __construct(mixed ...$args) // @phpstan-ignore constructor.unusedParameter
    {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
    }
}
