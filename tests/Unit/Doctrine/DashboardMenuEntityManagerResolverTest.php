<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\DashboardMenuBundle\Doctrine\DashboardMenuEntityManagerResolver;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Event\MenuTreeBuiltEvent;
use Nowo\DashboardMenuBundle\Model\MenuView;
use PHPUnit\Framework\TestCase;

final class DashboardMenuEntityManagerResolverTest extends TestCase
{
    public function testConfiguredManagerIsUsedAndResetByName(): void
    {
        $closed = $this->createStub(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('dashboard_menu')->willReturn($closed);
        $registry->expects(self::never())->method('getManagerForClass');
        $registry->expects(self::once())->method('resetManager')->with('dashboard_menu');

        $resolver = new DashboardMenuEntityManagerResolver($registry, 'dashboard_menu');
        self::assertSame($closed, $resolver->entityManager());
        $resolver->resetIfClosed();
    }

    public function testDefaultResolutionResetsTheManagerOfTheEntitiesByItsName(): void
    {
        $closed = $this->createStub(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(Menu::class)->willReturn($closed);
        $registry->method('getManagerNames')->willReturn(['other' => 'x', 'default' => 'y']);
        $registry->method('getManager')->willReturnCallback(fn (string $name): EntityManagerInterface => $name === 'default' ? $closed : $this->createStub(EntityManagerInterface::class));
        $registry->expects(self::once())->method('resetManager')->with('default');

        (new DashboardMenuEntityManagerResolver($registry))->resetIfClosed();
    }

    public function testOpenOrUnknownManagersAreNotReset(): void
    {
        $open = $this->createStub(EntityManagerInterface::class);
        $open->method('isOpen')->willReturn(true);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($open);
        $registry->expects(self::never())->method('resetManager');
        (new DashboardMenuEntityManagerResolver($registry))->resetIfClosed();

        $closed = $this->createStub(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $unnamed = $this->createMock(ManagerRegistry::class);
        $unnamed->method('getManagerForClass')->willReturn($closed);
        $unnamed->method('getManagerNames')->willReturn(['default' => 'y']);
        $unnamed->method('getManager')->willReturn($open);
        $unnamed->expects(self::never())->method('resetManager');
        (new DashboardMenuEntityManagerResolver($unnamed))->resetIfClosed();
    }

    public function testNoOrmManagerIsAClearError(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No Doctrine ORM entity manager maps');
        (new DashboardMenuEntityManagerResolver($registry))->entityManager();
    }

    public function testMenuViewAndTreeBuiltEvent(): void
    {
        $menu = (new Menu())->setCode('top')->setName('Top')->setClassMenu('nav');
        $view = MenuView::fromEntity($menu);
        self::assertSame('top', $view->code);
        self::assertSame('nav', $view->classes['menu']);
        self::assertNull($view->id);

        $event = new MenuTreeBuiltEvent('top', 'en', []);
        self::assertSame([], $event->getNodes());
        self::assertSame('top', $event->menuCode);
        self::assertSame('en', $event->locale);
    }
}
