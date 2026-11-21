<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Attribute;

use Nowo\DashboardMenuBundle\Attribute\AsMenuBadgeProvider;
use PHPUnit\Framework\TestCase;

final class AsMenuBadgeProviderTest extends TestCase
{
    public function testItemsAcceptsOneKeyOrAList(): void
    {
        self::assertSame(['a'], (new AsMenuBadgeProvider(item: 'a'))->items());
        self::assertSame(['a', 'b'], (new AsMenuBadgeProvider(item: ['a', 'b']))->items());
    }
}
