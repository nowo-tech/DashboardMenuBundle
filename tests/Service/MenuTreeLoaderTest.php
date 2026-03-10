<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class MenuTreeLoaderTest extends TestCase
{
    public function testLoadTreeReturnsEmptyArrayWhenMenuNotFound(): void
    {
        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findOneByCode')->with('unknown')->willReturn(null);
        $itemRepo = $this->createMock(MenuItemRepository::class);
        $config   = [
            'project'  => null,
            'defaults' => [
                'connection'         => 'default',
                'table_prefix'       => '',
                'permission_checker' => null,
                'cache_pool'         => null,
                'cache_ttl'          => 300,
            ],
            'menus' => [],
        ];
        $resolver  = new MenuConfigResolver($config, $menuRepo);
        $container = $this->createStub(ContainerInterface::class);

        $loader = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $container,
            new AllowAllMenuPermissionChecker(),
        );
        $tree = $loader->loadTree('unknown', 'en');

        self::assertSame([], $tree);
    }
}
