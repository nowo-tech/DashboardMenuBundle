<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\PermissionCheckerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PermissionCheckerPassTest extends TestCase
{
    public function testProcessBuildsParameterFromTaggedServices(): void
    {
        $container = new ContainerBuilder();
        $container->register('checker_a')->addTag('nowo_dashboard_menu.permission_checker');
        $container->register('checker_b')->addTag('nowo_dashboard_menu.permission_checker', ['label' => 'Custom B']);
        $container->register('checker_c')->addTag('nowo_dashboard_menu.permission_checker', ['label' => 'Custom C']);

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertIsArray($choices);
        self::assertSame('checker_a', $choices['checker_a']);
        self::assertSame('Custom B', $choices['checker_b']);
        self::assertSame('Custom C', $choices['checker_c']);
    }

    public function testProcessUsesServiceIdWhenLabelNotString(): void
    {
        $container = new ContainerBuilder();
        $container->register('my_checker')->addTag('nowo_dashboard_menu.permission_checker', ['label' => 123]);

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertIsArray($choices);
        self::assertSame('my_checker', $choices['my_checker']);
    }

    public function testProcessSortsChoicesByNaturalOrder(): void
    {
        $container = new ContainerBuilder();
        $container->register('z_checker')->addTag('nowo_dashboard_menu.permission_checker');
        $container->register('a_checker')->addTag('nowo_dashboard_menu.permission_checker');

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertIsArray($choices);
        $ids = array_keys($choices);
        self::assertSame('a_checker', $ids[0]);
        self::assertSame('z_checker', $ids[1]);
    }

    public function testProcessWithNoTaggedServicesSetsEmptyArray(): void
    {
        $container = new ContainerBuilder();

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertIsArray($choices);
        self::assertSame([], $choices);
    }

    public function testProcessMergesConfigPermissionCheckerChoices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.permission_checker_choices', ['checker_a', 'checker_b']);
        $container->register('checker_a')->addTag('nowo_dashboard_menu.permission_checker', ['label' => 'From tag']);
        $container->register('checker_b')->addTag('nowo_dashboard_menu.permission_checker');

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertIsArray($choices);
        self::assertSame('From tag', $choices['checker_a'], 'Label comes from the service tag, not YAML');
        self::assertSame('checker_b', $choices['checker_b']);
    }

    public function testProcessRespectsConfigOrder(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.permission_checker_choices', ['z_first', 'a_second']);
        $container->register('a_second')->addTag('nowo_dashboard_menu.permission_checker', ['label' => 'A']);
        $container->register('z_first')->addTag('nowo_dashboard_menu.permission_checker', ['label' => 'Z']);

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertIsArray($choices);
        $ids = array_keys($choices);
        self::assertSame('z_first', $ids[0]);
        self::assertSame('a_second', $ids[1]);
    }

    public function testProcessFillsMissingOrderEntriesWithRemainingChoices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.permission_checker_choices', ['checker_a']);

        $container->register('checker_a')->addTag('nowo_dashboard_menu.permission_checker', ['label' => 'A']);
        $container->register('checker_b')->addTag('nowo_dashboard_menu.permission_checker', ['label' => 'B']);

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertIsArray($choices);
        $ids = array_keys($choices);
        self::assertSame('checker_a', $ids[0]);
        self::assertSame('checker_b', $ids[1]);
        self::assertSame('B', $choices['checker_b']);
    }

    public function testProcessWhenParameterIsNotArrayNormalizesToEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.permission_checker_choices', 'invalid');

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertIsArray($choices);
        self::assertSame([], $choices);
    }

    public function testProcessWhenConfigOrderIsNotArrayUsesNaturalSort(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.permission_checker_choices', [
            'order' => 'not_an_array',
        ]);
        $container->register('z_checker')->addTag('nowo_dashboard_menu.permission_checker');
        $container->register('a_checker')->addTag('nowo_dashboard_menu.permission_checker');

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertIsArray($choices);
        $ids = array_keys($choices);
        self::assertSame('a_checker', $ids[0]);
        self::assertSame('z_checker', $ids[1]);
    }

    public function testProcessOrderOmitsIdsNotInTaggedChoices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.permission_checker_choices', ['nonexistent', 'checker_a']);
        $container->register('checker_a')->addTag('nowo_dashboard_menu.permission_checker', ['label' => 'A']);

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertIsArray($choices);
        $ids = array_keys($choices);
        self::assertSame('checker_a', $ids[0]);
        self::assertCount(1, $choices);
    }
}
