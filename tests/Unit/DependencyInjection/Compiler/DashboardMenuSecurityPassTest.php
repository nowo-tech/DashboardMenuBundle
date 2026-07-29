<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\DashboardMenuSecurityPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber;
use Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(DashboardMenuSecurityPass::class)]
final class DashboardMenuSecurityPassTest extends TestCase
{
    public function testNoopWhenDashboardDisabled(): void
    {
        $container = $this->baseContainer(enabled: false);
        (new DashboardMenuSecurityPass())->process($container);
        self::assertFalse($container->hasDefinition(DashboardAccessSubscriber::class));
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
        self::assertFalse($container->hasDefinition(DashboardAccessSubscriber::class));
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
    }

    public function testNoopWhenAccessRolesEmpty(): void
    {
        $container = $this->baseContainer(allowUnauthenticated: false, accessRoles: []);
        $container->setDefinition('security.authorization_checker', new Definition());

        (new DashboardMenuSecurityPass())->process($container);

        self::assertFalse($container->hasDefinition(DashboardAccessSubscriber::class));
    }

    /**
     * @param list<string> $accessRoles
     */
    private function baseContainer(
        bool $enabled = true,
        bool $allowUnauthenticated = true,
        array $accessRoles = ['ROLE_ADMIN'],
    ): ContainerBuilder {
        $container = new ContainerBuilder();
        $container->setParameter(Configuration::ALIAS . '.dashboard.enabled', $enabled);
        $container->setParameter(Configuration::ALIAS . '.security.allow_unauthenticated', $allowUnauthenticated);
        $container->setParameter(Configuration::ALIAS . '.security.access_roles', $accessRoles);

        return $container;
    }
}
