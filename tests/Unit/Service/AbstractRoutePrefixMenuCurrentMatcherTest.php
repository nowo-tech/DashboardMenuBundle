<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\AbstractRoutePrefixMenuCurrentMatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AbstractRoutePrefixMenuCurrentMatcherTest extends TestCase
{
    public function testMatchesSectionRouteAgainstIndexMenuItem(): void
    {
        $matcher = new class extends AbstractRoutePrefixMenuCurrentMatcher {
            protected function routePrefixes(): array
            {
                return [
                    'admin_ops_defaults' => ['admin_ops_defaults'],
                    'admin_appearance' => ['admin_appearance'],
                ];
            }
        };

        $item = new MenuItem();
        $item->setRouteName('admin_ops_defaults');
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);

        $request = Request::create('/admin/ops-defaults/governance');
        $request->attributes->set('_route', 'admin_ops_defaults_section');

        self::assertTrue($matcher->isCurrent($item, $request, '/admin/ops-defaults'));

        $appearance = new MenuItem();
        $appearance->setRouteName('admin_appearance');
        self::assertFalse($matcher->isCurrent($appearance, $request, '/admin/appearance'));
    }

    public function testReturnsFalseWithoutRouteAttribute(): void
    {
        $matcher = new class extends AbstractRoutePrefixMenuCurrentMatcher {
            protected function routePrefixes(): array
            {
                return ['admin_ops_defaults' => ['admin_ops_defaults']];
            }
        };

        $item = new MenuItem();
        $item->setRouteName('admin_ops_defaults');

        self::assertFalse($matcher->isCurrent($item, Request::create('/x'), '/admin/ops-defaults'));
    }
}
