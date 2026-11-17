<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DependencyInjection\Compiler\LinkResolvers;

use Nowo\DashboardMenuBundle\Attribute\MenuLinkResolverLabel;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\AutoTagMenuLinkResolversPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\MenuLinkResolverPass;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemViewLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuLinkResolverInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;

final class AutoTagMenuLinkResolversPassTest extends TestCase
{
    public function testProcessAddsTagToServiceImplementingInterfaceWithoutTag(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.my_checker', StubResolverNoLabel::class);

        $this->processAutoTag($container);

        self::assertTrue($container->getDefinition('app.my_checker')->hasTag('nowo_dashboard_menu.menu_link_resolver'));
        $tags = $container->getDefinition('app.my_checker')->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertSame('app.my_checker', $tags[0]['label'] ?? null);
    }

    public function testProcessUsesDashboardLabelConstantWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_with_constant', StubResolverWithConstant::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_with_constant')->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertSame('Label from constant', $tags[0]['label'] ?? null);
    }

    public function testProcessUsesMenuLinkResolverLabelAttributeWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_with_attribute', StubResolverWithAttribute::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_with_attribute')->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertSame('Label from attribute', $tags[0]['label'] ?? null);
    }

    public function testProcessDoesNotOverrideExistingTag(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.already_tagged', StubResolverWithConstant::class)
            ->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 'Custom from YAML']);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.already_tagged')->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertCount(1, $tags);
        self::assertSame('Custom from YAML', $tags[0]['label'] ?? null);
    }

    public function testProcessSkipsServiceNotImplementingInterface(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.other_service', StubNotAResolver::class);

        $this->processAutoTag($container);

        self::assertFalse($container->getDefinition('app.other_service')->hasTag('nowo_dashboard_menu.menu_link_resolver'));
    }

    public function testAutoTagContinuesWhenClassExistsButDoesNotImplementInterface(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.not_a_checker', StubNotAResolver::class);

        $this->processAutoTag($container);

        self::assertFalse($container->getDefinition('app.not_a_checker')->hasTag('nowo_dashboard_menu.menu_link_resolver'));
    }

    public function testAutoTagContinuesWhenAutoloadThrowsDuringSubclassCheck(): void
    {
        $container = new ContainerBuilder();

        $brokenClass = 'Nowo\\DashboardMenuBundle\\Tests\\DependencyInjection\\Compiler\\LinkResolvers\\BrokenAutoloadCheckerNonExisting';
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
            self::assertFalse($container->getDefinition($serviceId)->hasTag('nowo_dashboard_menu.menu_link_resolver'));
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    public function testAutoTagUsesAttributeLabelWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.invalid_attr_type', StubResolverInvalidAttributeType::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.invalid_attr_type')->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertSame('123', $tags[0]['label'] ?? null);
    }

    public function testProcessTagsDefinitionWhoseIdIsItsClassName(): void
    {
        $container = new ContainerBuilder();
        $container->register(StubResolverNoLabel::class, StubResolverNoLabel::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition(StubResolverNoLabel::class)->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertCount(1, $tags);
        self::assertSame(StubResolverNoLabel::class, $tags[0]['label'] ?? null);
    }

    public function testProcessUsesServiceIdAsClassWhenDefinitionHasNoClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(StubResolverWithConstant::class, new Definition());

        $this->processAutoTag($container);

        $tags = $container->getDefinition(StubResolverWithConstant::class)->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertSame('Label from constant', $tags[0]['label'] ?? null);
    }

    public function testProcessKeepsLabelOfAlreadyTaggedDefinitionWhoseIdIsItsClassName(): void
    {
        $container = new ContainerBuilder();
        $container->register(StubResolverWithConstant::class, StubResolverWithConstant::class)
            ->addTag('nowo_dashboard_menu.menu_link_resolver', ['label' => 'Custom from YAML']);

        $this->processAutoTag($container);

        $tags = $container->getDefinition(StubResolverWithConstant::class)->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertCount(1, $tags);
        self::assertSame('Custom from YAML', $tags[0]['label'] ?? null);
    }

    public function testProcessSkipsWhenClassDoesNotExist(): void
    {
        $container = new ContainerBuilder();

        // Coverage: line 46 (!class_exists($class)) => continue.
        $missing = 'Nowo\\DashboardMenuBundle\\Tests\\DependencyInjection\\Compiler\\LinkResolvers\\DefinitelyMissingChecker';
        $container->register('app.missing_class', $missing);

        $this->processAutoTag($container);

        self::assertFalse(
            $container->getDefinition('app.missing_class')->hasTag('nowo_dashboard_menu.menu_link_resolver'),
        );
    }

    public function testProcessSkipsDeprecatedDefinitionsWithoutAutoloadingTheClass(): void
    {
        $container = new ContainerBuilder();
        $class     = 'Nowo\\DashboardMenuBundle\\Tests\\DependencyInjection\\Compiler\\LinkResolvers\\DeprecatedShouldNotAutoload';
        $autoload  = static function (string $loaded) use ($class): void {
            if ($loaded === $class) {
                throw new RuntimeException('deprecated class was autoloaded');
            }
        };
        spl_autoload_register($autoload, true, true);

        try {
            $container->register('legacy.publisher', $class)
                ->setDeprecated('acme/legacy', '1.0', 'The "%service_id%" service is deprecated.');

            $this->processAutoTag($container);

            self::assertFalse(
                $container->getDefinition('legacy.publisher')->hasTag('nowo_dashboard_menu.menu_link_resolver'),
            );
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    public function testProcessSkipsInstanceofAndAbstractAndSyntheticDefinitions(): void
    {
        $container = new ContainerBuilder();
        $container->register('.instanceof.' . MenuLinkResolverInterface::class, StubResolverWithConstant::class);
        $container->register('.instanceof.foo', StubResolverWithConstant::class);
        $container->register('app.abstract', StubResolverWithConstant::class)->setAbstract(true);
        $container->register('app.synthetic', StubResolverWithConstant::class)->setSynthetic(true);

        $this->processAutoTag($container);

        self::assertFalse($container->getDefinition('.instanceof.' . MenuLinkResolverInterface::class)->hasTag('nowo_dashboard_menu.menu_link_resolver'));
        self::assertFalse($container->getDefinition('.instanceof.foo')->hasTag('nowo_dashboard_menu.menu_link_resolver'));
        self::assertFalse($container->getDefinition('app.abstract')->hasTag('nowo_dashboard_menu.menu_link_resolver'));
        self::assertFalse($container->getDefinition('app.synthetic')->hasTag('nowo_dashboard_menu.menu_link_resolver'));
    }

    public function testProcessFallsBackToServiceIdWhenConstantNotPublicAndAttributeEmpty(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_fallback', StubResolverFallbackLabel::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_fallback')->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertSame('app.stub_fallback', $tags[0]['label'] ?? null);
    }

    public function testProcessUsesAttributeWhenConstantIsEmptyString(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_empty_constant', StubResolverEmptyConstantWithAttribute::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_empty_constant')->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertSame('Label when constant empty', $tags[0]['label'] ?? null);
    }

    public function testProcessFallsBackToServiceIdWhenConstantEmptyAndNoAttribute(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_empty_only', StubResolverEmptyConstantOnly::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_empty_only')->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertSame('app.stub_empty_only', $tags[0]['label'] ?? null);
    }

    public function testProcessFallsBackToServiceIdWhenConstantIsNotStringAndNoAttribute(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.stub_non_string', StubResolverNonStringConstant::class);

        $this->processAutoTag($container);

        $tags = $container->getDefinition('app.stub_non_string')->getTag('nowo_dashboard_menu.menu_link_resolver');
        self::assertSame('app.stub_non_string', $tags[0]['label'] ?? null);
    }

    public function testAutoTagRunsBeforeMenuLinkResolverPassSoChoicesIncludeAutoTagged(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_dashboard_menu.menu_link_resolver_choices', []);
        $container->register('app.my_checker', StubResolverWithConstant::class);

        $container->addCompilerPass(new AutoTagMenuLinkResolversPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 200);
        $container->addCompilerPass(new MenuLinkResolverPass());
        $container->compile();

        $choices = $container->getParameter('nowo_dashboard_menu.menu_link_resolver_choices');
        self::assertIsArray($choices);
        self::assertArrayHasKey('app.my_checker', $choices);
        self::assertSame('Label from constant', $choices['app.my_checker']);
    }

    public function testProcessTagsResolversOfTheViewAndContextInterfaces(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.view_resolver', StubViewResolver::class);
        $container->register('app.context_resolver', StubContextResolver::class);

        $this->processAutoTag($container);

        self::assertTrue($container->getDefinition('app.view_resolver')->hasTag('nowo_dashboard_menu.menu_link_resolver'));
        self::assertTrue($container->getDefinition('app.context_resolver')->hasTag('nowo_dashboard_menu.menu_link_resolver'));
    }

    private function processAutoTag(ContainerBuilder $container): void
    {
        (new AutoTagMenuLinkResolversPass())->process($container);
    }
}

// Stub classes for testing (same namespace so they live in the same file and are autoloaded with the test)

class StubResolverNoLabel implements MenuLinkResolverInterface
{
    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        return '/';
    }
}

class StubResolverWithConstant implements MenuLinkResolverInterface
{
    public const DASHBOARD_LABEL = 'Label from constant';

    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        return '/';
    }
}

#[MenuLinkResolverLabel('Label from attribute')]
class StubResolverWithAttribute implements MenuLinkResolverInterface
{
    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        return '/';
    }
}

/** Class that does not implement MenuLinkResolverInterface (for testProcessSkipsServiceNotImplementingInterface). */
class StubNotAResolver
{
}

// Coverage: constant is not public and attribute label empty => fallback to service id.
#[MenuLinkResolverLabel('')]
class StubResolverFallbackLabel implements MenuLinkResolverInterface
{
    /** @phpstan-ignore classConstant.unused */
    private const DASHBOARD_LABEL = 'private label';

    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        return '/';
    }
}

// Coverage: public constant exists but is empty string => labelFromConstant returns null, use attribute.
#[MenuLinkResolverLabel('Label when constant empty')]
class StubResolverEmptyConstantWithAttribute implements MenuLinkResolverInterface
{
    public const DASHBOARD_LABEL = '';

    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        return '/';
    }
}

// Coverage: public constant empty string, no attribute => fallback to service id.
class StubResolverEmptyConstantOnly implements MenuLinkResolverInterface
{
    public const DASHBOARD_LABEL = '';

    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        return '/';
    }
}

#[MenuLinkResolverLabel('123')]
class StubResolverInvalidAttributeType implements MenuLinkResolverInterface
{
    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        return '/';
    }
}

// Coverage: public constant is not a string => ignored, fallback to service id.
class StubResolverNonStringConstant implements MenuLinkResolverInterface
{
    public const DASHBOARD_LABEL = 123;

    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        return '/';
    }
}

class StubViewResolver implements MenuItemViewLinkResolverInterface
{
    public function resolveItemHref(MenuItemView $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        return '/';
    }
}

class StubContextResolver implements MenuItemContextLinkResolverInterface
{
    public function resolveItemHrefFor(MenuItemView $item, ?MenuRequestContext $request, MenuPermissionContext $context): string|array
    {
        return '/';
    }
}
