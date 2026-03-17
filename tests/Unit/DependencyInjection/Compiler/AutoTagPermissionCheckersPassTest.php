<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\AutoTagPermissionCheckersPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\PermissionCheckerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AutoTagPermissionCheckersPassTest extends TestCase
{
    public function testProcessAddsTagToServiceImplementingInterfaceWithoutTag(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.my_checker', StubCheckerNoLabel::class);

        $this->processAutoTag($container);

        self::assertTrue($container->getDefinition('app.my_checker')->hasTag('nowo_dashboard_menu.permission_checker'));
        $tags = $container->getDefinition('app.my_checker')->getTag('nowo_dashboard_menu.permission_checker');
        self::assertSame('app.my_checker', $tags[0]['label'] ?? null);
    }

    public function testProcessUsesDashboardLabelConstantWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_with_constant', StubCheckerWithConstant::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_with_constant')->getTag('nowo_dashboard_menu.permission_checker');
        self::assertSame('Label from constant', $tags[0]['label'] ?? null);
    }

    public function testProcessUsesPermissionCheckerLabelAttributeWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_with_attribute', StubCheckerWithAttribute::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_with_attribute')->getTag('nowo_dashboard_menu.permission_checker');
        self::assertSame('Label from attribute', $tags[0]['label'] ?? null);
    }

    public function testProcessDoesNotOverrideExistingTag(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.already_tagged', StubCheckerWithConstant::class)
            ->addTag('nowo_dashboard_menu.permission_checker', ['label' => 'Custom from YAML']);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.already_tagged')->getTag('nowo_dashboard_menu.permission_checker');
        self::assertCount(1, $tags);
        self::assertSame('Custom from YAML', $tags[0]['label'] ?? null);
    }

    public function testProcessSkipsServiceNotImplementingInterface(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.other_service', \stdClass::class);

        $this->processAutoTag($container);

        self::assertFalse($container->getDefinition('app.other_service')->hasTag('nowo_dashboard_menu.permission_checker'));
    }

    public function testAutoTagRunsBeforePermissionCheckerPassSoChoicesIncludeAutoTagged(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.permission_checker_choices', ['order' => [], 'labels' => []]);
        $container->register('app.my_checker', StubCheckerWithConstant::class);

        $container->addCompilerPass(new AutoTagPermissionCheckersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 200);
        $container->addCompilerPass(new PermissionCheckerPass());
        $container->compile();

        $choices = $container->getParameter('nowo_dashboard_menu.permission_checker_choices');
        self::assertArrayHasKey('app.my_checker', $choices);
        self::assertSame('Label from constant', $choices['app.my_checker']);
    }

    private function processAutoTag(ContainerBuilder $container): void
    {
        (new AutoTagPermissionCheckersPass())->process($container);
    }
}

// Stub classes for testing (same namespace so they live in the same file and are autoloaded with the test)

use Nowo\DashboardMenuBundle\Attribute\PermissionCheckerLabel;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;

class StubCheckerNoLabel implements MenuPermissionCheckerInterface
{
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }
}

class StubCheckerWithConstant implements MenuPermissionCheckerInterface
{
    public const DASHBOARD_LABEL = 'Label from constant';

    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }
}

#[PermissionCheckerLabel('Label from attribute')]
class StubCheckerWithAttribute implements MenuPermissionCheckerInterface
{
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }
}
