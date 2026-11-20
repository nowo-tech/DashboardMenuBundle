<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Concurrency;

use RuntimeException;

/**
 * A form was built from an older version of a menu or an item than the one stored now (2.24, REQ-RUNTIME-003 rule 4):
 * another admin saved meanwhile, or the row is gone. A domain answer (the dashboard flashes "changed meanwhile" and
 * shows the current state), never a `CONFLICT_001` / `CONFLICT_002`; thrown inside a write unit, so nothing is saved.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class StaleEditException extends RuntimeException
{
    public const MESSAGE = 'The menu or item changed while it was being edited.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
