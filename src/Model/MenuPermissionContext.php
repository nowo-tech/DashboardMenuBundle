<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Model;

use Nowo\DashboardMenuBundle\Service\PermissionKeyAwareMenuPermissionChecker;

use function array_is_list;
use function array_key_exists;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * What a permission checker or a link resolver may know about the menu being built, replacing the `mixed`
 * permission context of the deprecated ports.
 *
 * - {@see $request}: the request the menu is rendered for, as a {@see MenuRequestContext} (null for a context the
 *   host passed explicitly, for the console or for a Messenger handler).
 * - {@see $attributes}: plain values the host passed (an array given to `dashboard_menu_tree(code, {...})` or to
 *   `MenuTreeLoader::loadTree()`), e.g. `{tenant: 'acme', role: 'editor'}`. Only scalar, null and lists of those with
 *   string keys are kept; an object is never carried.
 *
 * It holds no user object: resolve the current user through your own port (security, session) in the checker.
 * Unknown permission keys stay the checker's decision; the bundled {@see PermissionKeyAwareMenuPermissionChecker}
 * hides every item that has keys (deny by default).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuPermissionContext
{
    /** @var array<string, bool|float|int|list<bool|float|int|string|null>|string|null> */
    public array $attributes;

    /**
     * @param MenuRequestContext|null $request the request the menu is rendered for, or null
     * @param array<array-key, mixed> $attributes host values; non-scalar entries (objects, nested maps) and
     *                                            non-string keys are dropped
     */
    public function __construct(
        public ?MenuRequestContext $request = null,
        array $attributes = [],
    ) {
        $kept = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name)) {
                continue;
            }
            if ($value === null || is_scalar($value)) {
                $kept[$name] = $value;
            } elseif (is_array($value) && array_is_list($value) && $this->allScalar($value)) {
                $kept[$name] = $value;
            }
        }
        $this->attributes = $kept;
    }

    /**
     * One attribute.
     *
     * @param string $name attribute name
     *
     * @return bool|float|int|list<bool|float|int|string|null>|string|null the value, or null when absent
     */
    public function attribute(string $name): bool|float|int|string|array|null
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Whether an attribute is present.
     *
     * @param string $name attribute name
     */
    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * @param list<mixed> $values
     */
    private function allScalar(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && !is_scalar($value)) {
                return false;
            }
        }

        return true;
    }
}
