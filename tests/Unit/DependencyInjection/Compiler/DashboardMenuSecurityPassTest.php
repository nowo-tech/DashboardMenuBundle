<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\DashboardMenuSecurityPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber;
use Nowo\DashboardMenuBundle\Security\AllowAllDashboardMenuAccessChecker;
use Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(DashboardMenuSecurityPass::class)]
final class DashboardMenuSecurityPassTest extends TestCase
{
    public function testNoopWhenDashboardParameterMissing(): void
    {
        $container = new ContainerBuilder();
        (new DashboardMenuSecurityPass())->process($container);
        self::assertFalse($container->hasDefinition(DashboardAccessSubscriber::class));
    }

    public function testRegistersNotFoundSubscriberWhenDashboardDisabled(): void
    {
        // Regression: dashboard.enabled false skipped the subscriber while routes_dashboard.yaml could still be imported.
        $container = $this->baseContainer(enabled: false, allowUnauthenticated: false);
        (new DashboardMenuSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(DashboardAccessSubscriber::class));
        $definition = $container->getDefinition(DashboardAccessSubscriber::class);
        self::assertFalse($definition->getArgument('$dashboardEnabled'));
        self::assertTrue($definition->hasTag('kernel.event_subscriber'));
    }

    public function testFailsWithoutSecurityWhenNotAllowUnauthenticated(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false);

        $this->expectException(InvalidConfigurationException::class);
        (new DashboardMenuSecurityPass())->process($container);
    }

    public function testAllowsMissingSecurityWhenAllowUnauthenticated(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: true);
        (new DashboardMenuSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(DashboardAccessSubscriber::class));
        $definition = $container->getDefinition(DashboardAccessSubscriber::class);
        self::assertTrue($definition->getArgument('$dashboardEnabled'));
        $checker = $definition->getArgument('$accessChecker');
        self::assertInstanceOf(Definition::class, $checker);
        self::assertSame(AllowAllDashboardMenuAccessChecker::class, $checker->getClass());
    }

    public function testRegistersSubscriberWhenSecurityPresent(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false);
        $container->setDefinition('security.authorization_checker', new Definition());
        $container->setAlias(DashboardMenuAccessCheckerInterface::class, 'security.authorization_checker');

        (new DashboardMenuSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(DashboardAccessSubscriber::class));
        $definition = $container->getDefinition(DashboardAccessSubscriber::class);
        self::assertSame(DashboardAccessSubscriber::class, $definition->getClass());
        self::assertTrue($definition->hasTag('kernel.event_subscriber'));
        self::assertTrue($definition->getArgument('$dashboardEnabled'));
        self::assertInstanceOf(Reference::class, $definition->getArgument('$accessChecker'));
    }

    public function testRegistersSubscriberWhenAccessRolesEmpty(): void
    {
        // Regression: access_roles: [] without a custom checker used to skip the subscriber (dashboard open).
        $container = $this->baseContainer(allowUnauthenticated: false, accessRoles: []);
        $container->setDefinition('security.authorization_checker', new Definition());

        (new DashboardMenuSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(DashboardAccessSubscriber::class));
        self::assertInstanceOf(Reference::class, $container->getDefinition(DashboardAccessSubscriber::class)->getArgument('$accessChecker'));
    }

    public function testDoesNotDuplicateExistingSubscriber(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false);
        $container->setDefinition('security.authorization_checker', new Definition());
        $existing = new Definition();
        $container->setDefinition(DashboardAccessSubscriber::class, $existing);

        (new DashboardMenuSecurityPass())->process($container);

        self::assertSame($existing, $container->getDefinition(DashboardAccessSubscriber::class));
    }

    public function testRegistersSubscriberWhenCustomCheckerDespiteEmptyRoles(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false, accessRoles: [], customAccessChecker: true);
        $container->setDefinition('security.authorization_checker', new Definition());
        $container->setAlias(DashboardMenuAccessCheckerInterface::class, 'security.authorization_checker');

        (new DashboardMenuSecurityPass())->process($container);

        self::assertTrue($container->hasDefinition(DashboardAccessSubscriber::class));
    }

    /**
     * @param list<string> $accessRoles
     */
    private function baseContainer(
        bool $enabled = true,
        bool $allowUnauthenticated = true,
        array $accessRoles = ['ROLE_ADMIN'],
        bool $customAccessChecker = false,
    ): ContainerBuilder {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS . '.dashboard.enabled', $enabled);
        $container->setParameter(Configuration::ALIAS . '.security.allow_unauthenticated', $allowUnauthenticated);
        $container->setParameter(Configuration::ALIAS . '.security.access_roles', $accessRoles);
        $container->setParameter(Configuration::ALIAS . '.security.custom_access_checker', $customAccessChecker);

        return $container;
    }
}
