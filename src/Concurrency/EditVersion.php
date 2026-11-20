<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Concurrency;

use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;

use function is_int;
use function is_string;
use function preg_match;

/**
 * The optimistic check of the dashboard edit forms (2.24, REQ-RUNTIME-003 rule 4). A form carries the `edit_version` of
 * the row it was built from; inside the write unit the stored version is read from the database (never the unit of
 * work, which holds what the request loaded) under a `PESSIMISTIC_WRITE` lock of the row, and a different one means
 * another admin saved meanwhile: {@see StaleEditException}, nothing saved. The caller bumps the version when it
 * passes ({@see Menu::bumpEditVersion()}, {@see MenuItem::bumpEditVersion()}).
 *
 * Static and stateless (FrankenPHP worker mode, kernel reset off): nothing is kept between calls.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class EditVersion
{
    /**
     * Version a form posted: a non-negative integer string, else null (a missing field, a form rendered before 2.24).
     */
    public static function posted(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        return is_string($value) && preg_match('/^\d{1,9}$/', $value) === 1 ? (int) $value : null;
    }

    /**
     * The submit token of a new-item form: 16 to 64 letters or digits, else '' (no token: the item is created without
     * the double-submit guard, as before 2.24).
     */
    public static function submitToken(mixed $value): string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9]{16,64}$/', $value) === 1 ? $value : '';
    }

    /**
     * @param int|null $stored the version read from the database under the row lock ({@see MenuRepository::storedEditVersion()},
     *                         {@see MenuItemRepository::storedEditVersion()}), null when the row is gone
     * @param int|null $posted the version the form carried ({@see self::posted()})
     *
     * @throws StaleEditException when the stored version is not the posted one, or the row is gone
     */
    public static function assertCurrent(?int $stored, ?int $posted): void
    {
        if ($stored === null || $posted === null || $stored !== $posted) {
            throw new StaleEditException();
        }
    }
}
