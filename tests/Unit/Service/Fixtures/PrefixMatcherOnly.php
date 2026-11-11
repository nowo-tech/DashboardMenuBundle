<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures;

use Nowo\DashboardMenuBundle\Service\AbstractRoutePrefixMenuCurrentMatcher;

/**
 * Route-prefix matcher that only defines its prefixes (both variants come from the bundle base class).
 */
final class PrefixMatcherOnly extends AbstractRoutePrefixMenuCurrentMatcher
{
    protected function routePrefixes(): array
    {
        return ['admin_index' => ['admin_']];
    }
}
