<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\MappingException;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\DashboardMenuEntityManagerPass;
use Nowo\DashboardMenuBundle\Doctrine\ExcludedEntityNamespaceDriver;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\TestCase;

final class ExcludedEntityNamespaceDriverTest extends TestCase
{
    public function testMapsNothing(): void
    {
        $driver = new ExcludedEntityNamespaceDriver();

        self::assertTrue($driver->isTransient(Menu::class));
        self::assertSame([], $driver->getAllClassNames());

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(Menu::class);
        $driver->loadMetadataForClass(Menu::class, new ClassMetadata(Menu::class));
    }

    /**
     * A chain asks its namespace drivers before its default driver: a default driver that claims every entity class
     * no longer claims the menu entities.
     */
    public function testWinsOverTheDefaultDriverOfAChain(): void
    {
        $catchAll = $this->createStub(MappingDriver::class);
        $catchAll->method('isTransient')->willReturn(false);
        $chain = new MappingDriverChain();
        $chain->setDefaultDriver($catchAll);
        self::assertFalse($chain->isTransient(Menu::class));

        $chain->addDriver(new ExcludedEntityNamespaceDriver(), DashboardMenuEntityManagerPass::ENTITY_NAMESPACE);

        self::assertTrue($chain->isTransient(Menu::class));
        self::assertTrue($chain->isTransient(MenuItem::class));
        self::assertFalse($chain->isTransient(self::class), 'other classes still go to the default driver');
    }
}
