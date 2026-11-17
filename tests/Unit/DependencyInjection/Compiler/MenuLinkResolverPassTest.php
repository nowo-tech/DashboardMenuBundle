<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\MenuLinkResolverPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MenuLinkResolverPassTest extends TestCase
{
    public function testProcessBuildsParameterFromTaggedServices(): void
    {
        $container = new ContainerBuilder();
        $container->register('resolver_a')->addTag('nowo_dashboard_menu.menu_link_resolver');
        $container->register('resolver_b')->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 'Custom B']);
        $container->register('resolver_c')->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 'Custom C']);

        $pass = new MenuLinkResolverPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        self::assertSame('resolver_a', $choices['resolver_a']);
        self::assertSame('Custom B', $choices['resolver_b']);
        self::assertSame('Custom C', $choices['resolver_c']);
    }

    public function testProcessUsesServiceIdWhenLabelNotString(): void
    {
        $container = new ContainerBuilder();
        $container->register('my_resolver')->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 123]);

        $pass = new MenuLinkResolverPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        self::assertSame('my_resolver', $choices['my_resolver']);
    }

    public function testProcessSortsChoicesByNaturalOrder(): void
    {
        $container = new ContainerBuilder();
        $container->register('z_checker')->addTag('nowo_dashboard_menu.menu_link_resolver');
        $container->register('a_checker')->addTag('nowo_dashboard_menu.menu_link_resolver');

        $pass = new MenuLinkResolverPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        $ids = array_keys($choices);
        self::assertSame('a_checker', $ids[0]);
        self::assertSame('z_checker', $ids[1]);
    }

    public function testProcessWithNoTaggedServicesSetsEmptyArray(): void
    {
        $container = new ContainerBuilder();

        $pass = new MenuLinkResolverPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        self::assertSame([], $choices);
    }

    public function testProcessMergesConfigPermissionCheckerChoices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.menu_link_resolver_choices', ['resolver_a', 'resolver_b']);
        $container->register('resolver_a')->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 'From tag']);
        $container->register('resolver_b')->addTag('nowo_dashboard_menu.menu_link_resolver');

        $pass = new MenuLinkResolverPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        self::assertSame('From tag', $choices['resolver_a'], 'Label comes from the service tag, not YAML');
        self::assertSame('resolver_b', $choices['resolver_b']);
    }

    public function testProcessRespectsConfigOrder(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.menu_link_resolver_choices', ['z_first', 'a_second']);
        $container->register('a_second')->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 'A']);
        $container->register('z_first')->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 'Z']);

        $pass = new MenuLinkResolverPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        $ids = array_keys($choices);
        self::assertSame('z_first', $ids[0]);
        self::assertSame('a_second', $ids[1]);
    }

    public function testProcessFillsMissingOrderEntriesWithRemainingChoices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.menu_link_resolver_choices', ['resolver_a']);

        $container->register('resolver_a')->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 'A']);
        $container->register('resolver_b')->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 'B']);

        $pass = new MenuLinkResolverPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        $ids = array_keys($choices);
        self::assertSame('resolver_a', $ids[0]);
        self::assertSame('resolver_b', $ids[1]);
        self::assertSame('B', $choices['resolver_b']);
    }

    public function testProcessWhenParameterIsNotArrayNormalizesToEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.menu_link_resolver_choices', 'invalid');

        $pass = new MenuLinkResolverPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        self::assertSame([], $choices);
    }

    public function testProcessWhenConfigOrderIsNotArrayUsesNaturalSort(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.menu_link_resolver_choices', [
            'order' => 'not_an_array',
        ]);
        $container->register('z_checker')->addTag('nowo_dashboard_menu.menu_link_resolver');
        $container->register('a_checker')->addTag('nowo_dashboard_menu.menu_link_resolver');

        $pass = new MenuLinkResolverPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        $ids = array_keys($choices);
        self::assertSame('a_checker', $ids[0]);
        self::assertSame('z_checker', $ids[1]);
    }

    public function testProcessOrderOmitsIdsNotInTaggedChoices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.menu_link_resolver_choices', ['nonexistent', 'resolver_a']);
        $container->register('resolver_a')->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 'A']);

        $pass = new MenuLinkResolverPass();
        $pass->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        $ids = array_keys($choices);
        self::assertSame('resolver_a', $ids[0]);
        self::assertCount(1, $choices);
    }

    public function testProcessReadsTheOrderKeyOfAMapConfig(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.menu_link_resolver_choices', ['order' => ['z_first', 7, 'a_second']]);
        $container->register('a_second')->addTag('nowo_dashboard_menu.menu_link_resolver');
        $container->register('z_first')->addTag('nowo_dashboard_menu.menu_link_resolver');

        (new MenuLinkResolverPass())->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        self::assertSame(['z_first', 'a_second'], array_keys($choices));
    }

    public function testProcessIgnoresAMapConfigWithoutAnOrderList(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.menu_link_resolver_choices', ['other' => 'x']);
        $container->register('b_one')->addTag('nowo_dashboard_menu.menu_link_resolver');
        $container->register('a_one')->addTag('nowo_dashboard_menu.menu_link_resolver');

        (new MenuLinkResolverPass())->process($container);

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        self::assertSame(['a_one', 'b_one'], array_keys($choices));
    }
}
