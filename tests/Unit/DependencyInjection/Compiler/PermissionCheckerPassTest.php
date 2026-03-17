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
        $ids     = array_keys($choices);
        self::assertSame('a_checker', $ids[0]);
        self::assertSame('z_checker', $ids[1]);
    }

    public function testProcessWithNoTaggedServicesSetsEmptyArray(): void
    {
        $container = new ContainerBuilder();

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        self::assertSame([], $container->getParameter('nowo_dashboard_menu.permission_checker_choices'));
    }

    public function testProcessMergesConfigPermissionCheckerChoices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.permission_checker_choices', [
            'checker_a'     => 'Label from YAML',
            'extra_checker' => 'Only in config',
        ]);
        $container->register('checker_a')->addTag('nowo_dashboard_menu.permission_checker', ['label' => 'From tag']);
        $container->register('checker_b')->addTag('nowo_dashboard_menu.permission_checker');

        $pass = new PermissionCheckerPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertSame('Label from YAML', $choices['checker_a'], 'Config overrides tag label');
        self::assertSame('checker_b', $choices['checker_b']);
        self::assertSame('Only in config', $choices['extra_checker'], 'Config can add entries without tag');
    }
}
