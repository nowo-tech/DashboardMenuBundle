<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\Attribute\MenuLinkResolverLabel;
use Nowo\DashboardMenuBundle\Service\MenuItemContextLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemViewLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuLinkResolverInterface;
use ReflectionClass;
use ReflectionClassConstant;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Throwable;

use function is_string;

/**
 * Tags services implementing MenuLinkResolverInterface (or MenuItemViewLinkResolverInterface) with nowo_dashboard_menu.menu_link_resolver.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AutoTagMenuLinkResolversPass implements CompilerPassInterface
{
    private const TAG = 'nowo_dashboard_menu.menu_link_resolver';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($this->shouldSkip($definition, $id)) {
                continue;
            }

            // With resource loading the service id is the class name and getClass() may be null (same as the checker pass).
            $class = $definition->getClass() ?? $id;

            try {
                if (!class_exists($class)) {
                    continue;
                }

                if (!is_subclass_of($class, MenuLinkResolverInterface::class) && !is_subclass_of($class, MenuItemViewLinkResolverInterface::class) && !is_subclass_of($class, MenuItemContextLinkResolverInterface::class)) {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }

            if ($definition->hasTag(self::TAG)) {
                continue;
            }

            try {
                $label = $this->resolveLabel($class, $id);
            } catch (Throwable) {
                $label = $id;
            }

            $definition->addTag(self::TAG, ['label' => $label]);
        }
    }

    private function shouldSkip(Definition $definition, string $id): bool
    {
        if ($definition->isAbstract() || $definition->isSynthetic() || $definition->isDeprecated()) {
            return true;
        }

        return $id === '.instanceof.' . MenuLinkResolverInterface::class
            || str_starts_with($id, '.instanceof.');
    }

    /**
     * @param class-string $class
     */
    private function resolveLabel(string $class, string $serviceId): string
    {
        $reflection = new ReflectionClass($class);

        $label = $this->labelFromConstant($reflection);
        if (is_string($label)) {
            return $label;
        }

        $label = $this->labelFromAttribute($reflection);
        if (is_string($label)) {
            return $label;
        }

        return $serviceId;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function labelFromAttribute(ReflectionClass $reflection): ?string
    {
        $attrs = $reflection->getAttributes(MenuLinkResolverLabel::class);
        foreach ($attrs as $attr) {
            $instance = $attr->newInstance();
            if ($instance->label !== '') {
                return $instance->label;
            }
        }

        return null;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function labelFromConstant(ReflectionClass $reflection): ?string
    {
        if (!$reflection->hasConstant('DASHBOARD_LABEL')) {
            return null;
        }

        $const = $reflection->getReflectionConstant('DASHBOARD_LABEL');
        if (!$const instanceof ReflectionClassConstant || !$const->isPublic()) {
            return null;
        }

        $value = $const->getValue();
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
