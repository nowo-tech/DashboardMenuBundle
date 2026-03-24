<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\Attribute\PermissionCheckerLabel;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\AutoTagPermissionCheckersPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\PermissionCheckerPass;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
        $container->register('app.other_service', StubNotAChecker::class);

        $this->processAutoTag($container);

        self::assertFalse($container->getDefinition('app.other_service')->hasTag('nowo_dashboard_menu.permission_checker'));
    }

    public function testAutoTagContinuesWhenClassExistsButDoesNotImplementInterface(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.not_a_checker', StubNotAChecker::class);

        $this->processAutoTag($container);

        self::assertFalse($container->getDefinition('app.not_a_checker')->hasTag('nowo_dashboard_menu.permission_checker'));
    }

    public function testAutoTagContinuesWhenAutoloadThrowsDuringSubclassCheck(): void
    {
        $container = new ContainerBuilder();

        $brokenClass = 'Nowo\\DashboardMenuBundle\\Tests\\DependencyInjection\\Compiler\\BrokenAutoloadCheckerNonExisting';
        $serviceId   = 'app.broken_autoload';

        // Ensure the class doesn't exist so class_exists triggers the autoloader.
        // We intentionally register an autoloader that throws for this one class.
        $autoload = static function (string $class) use ($brokenClass): void {
            if ($class === $brokenClass) {
                throw new RuntimeException('autoload boom');
            }
        };
        spl_autoload_register($autoload, prepend: true);

        try {
            $container->register($serviceId, $brokenClass);
            $this->processAutoTag($container);
            self::assertFalse($container->getDefinition($serviceId)->hasTag('nowo_dashboard_menu.permission_checker'));
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    public function testAutoTagUsesAttributeLabelWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.invalid_attr_type', StubCheckerInvalidAttributeType::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.invalid_attr_type')->getTag('nowo_dashboard_menu.permission_checker');
        self::assertSame('123', $tags[0]['label'] ?? null);
    }

    public function testProcessSkipsWhenDefinitionClassEqualsServiceId(): void
    {
        $container = new ContainerBuilder();
        // Coverage: line 41 (class === service id) => continue.
        $container->register('app.skip_same_id', 'app.skip_same_id');

        $this->processAutoTag($container);

        self::assertFalse(
            $container->getDefinition('app.skip_same_id')->hasTag('nowo_dashboard_menu.permission_checker'),
        );
    }

    public function testProcessSkipsWhenClassDoesNotExist(): void
    {
        $container = new ContainerBuilder();

        // Coverage: line 46 (!class_exists($class)) => continue.
        $missing = 'Nowo\\DashboardMenuBundle\\Tests\\DependencyInjection\\Compiler\\DefinitelyMissingChecker';
        $container->register('app.missing_class', $missing);

        $this->processAutoTag($container);

        self::assertFalse(
            $container->getDefinition('app.missing_class')->hasTag('nowo_dashboard_menu.permission_checker'),
        );
    }

    public function testProcessSkipsInstanceofAndAbstractAndSyntheticDefinitions(): void
    {
        $container = new ContainerBuilder();
        $container->register('.instanceof.' . MenuPermissionCheckerInterface::class, StubCheckerWithConstant::class);
        $container->register('.instanceof.foo', StubCheckerWithConstant::class);
        $container->register('app.abstract', StubCheckerWithConstant::class)->setAbstract(true);
        $container->register('app.synthetic', StubCheckerWithConstant::class)->setSynthetic(true);

        $this->processAutoTag($container);

        self::assertFalse($container->getDefinition('.instanceof.' . MenuPermissionCheckerInterface::class)->hasTag('nowo_dashboard_menu.permission_checker'));
        self::assertFalse($container->getDefinition('.instanceof.foo')->hasTag('nowo_dashboard_menu.permission_checker'));
        self::assertFalse($container->getDefinition('app.abstract')->hasTag('nowo_dashboard_menu.permission_checker'));
        self::assertFalse($container->getDefinition('app.synthetic')->hasTag('nowo_dashboard_menu.permission_checker'));
    }

    public function testProcessFallsBackToServiceIdWhenConstantNotPublicAndAttributeEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_fallback', StubCheckerFallbackLabel::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_fallback')->getTag('nowo_dashboard_menu.permission_checker');
        self::assertSame('app.stub_fallback', $tags[0]['label'] ?? null);
    }

    public function testProcessUsesAttributeWhenConstantIsEmptyString(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_empty_constant', StubCheckerEmptyConstantWithAttribute::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_empty_constant')->getTag('nowo_dashboard_menu.permission_checker');
        self::assertSame('Label when constant empty', $tags[0]['label'] ?? null);
    }

    public function testProcessFallsBackToServiceIdWhenConstantEmptyAndNoAttribute(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_empty_only', StubCheckerEmptyConstantOnly::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_empty_only')->getTag('nowo_dashboard_menu.permission_checker');
        self::assertSame('app.stub_empty_only', $tags[0]['label'] ?? null);
    }

    public function testProcessFallsBackToServiceIdWhenConstantIsNotStringAndNoAttribute(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_non_string', StubCheckerNonStringConstant::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_non_string')->getTag('nowo_dashboard_menu.permission_checker');
        self::assertSame('app.stub_non_string', $tags[0]['label'] ?? null);
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
        self::assertIsArray($choices);
        self::assertArrayHasKey('app.my_checker', $choices);
        self::assertSame('Label from constant', $choices['app.my_checker']);
    }

    private function processAutoTag(ContainerBuilder $container): void
    {
        (new AutoTagPermissionCheckersPass())->process($container);
    }
}

// Stub classes for testing (same namespace so they live in the same file and are autoloaded with the test)

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

/** Class that does not implement MenuPermissionCheckerInterface (for testProcessSkipsServiceNotImplementingInterface). */
class StubNotAChecker
{
}

// Coverage: constant is not public and attribute label empty => fallback to service id.
#[PermissionCheckerLabel('')]
class StubCheckerFallbackLabel implements MenuPermissionCheckerInterface
{
    /** @phpstan-ignore classConstant.unused */
    private const DASHBOARD_LABEL = 'private label';

    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }
}

// Coverage: public constant exists but is empty string => labelFromConstant returns null, use attribute.
#[PermissionCheckerLabel('Label when constant empty')]
class StubCheckerEmptyConstantWithAttribute implements MenuPermissionCheckerInterface
{
    public const DASHBOARD_LABEL = '';

    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }
}

// Coverage: public constant empty string, no attribute => fallback to service id.
class StubCheckerEmptyConstantOnly implements MenuPermissionCheckerInterface
{
    public const DASHBOARD_LABEL = '';

    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }
}

#[PermissionCheckerLabel('123')]
class StubCheckerInvalidAttributeType implements MenuPermissionCheckerInterface
{
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }
}

// Coverage: public constant is not a string => ignored, fallback to service id.
class StubCheckerNonStringConstant implements MenuPermissionCheckerInterface
{
    public const DASHBOARD_LABEL = 123;

    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }
}
