<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\EventSubscriber\TablePrefixSubscriber;
use PHPUnit\Framework\TestCase;
use stdClass;

final class TablePrefixSubscriberTest extends TestCase
{
    public function testLoadClassMetadataWithEmptyPrefixDoesNotChangeMetadata(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(Menu::class);
        $metadata->expects(self::never())->method('setPrimaryTable');

        $args = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        $subscriber = new TablePrefixSubscriber('');
        $subscriber->loadClassMetadata($args);
    }

    public function testLoadClassMetadataWithPrefixSetsMenuTableName(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(Menu::class);
        $metadata->method('getTableName')->willReturn('menu');
        $metadata->expects(self::once())
            ->method('setPrimaryTable')
            ->with(self::identicalTo(['name' => 'app_menu']));

        $args = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        $subscriber = new TablePrefixSubscriber('app_');
        $subscriber->loadClassMetadata($args);
    }

    public function testLoadClassMetadataWithPrefixSetsMenuItemTableName(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(MenuItem::class);
        $metadata->method('getTableName')->willReturn('menu_item');
        $metadata->expects(self::once())
            ->method('setPrimaryTable')
            ->with(self::identicalTo(['name' => 'app_menu_item']));

        $args = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        $subscriber = new TablePrefixSubscriber('app_');
        $subscriber->loadClassMetadata($args);
    }

    public function testLoadClassMetadataWithOtherEntityDoesNothing(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn(stdClass::class);
        $metadata->expects(self::never())->method('setPrimaryTable');

        $args = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        $subscriber = new TablePrefixSubscriber('app_');
        $subscriber->loadClassMetadata($args);
    }
}
