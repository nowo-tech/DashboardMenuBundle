<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\MenuRequestContextFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.20: `MenuRequestContextFactory::fromRequest()` is a supported public API, so hosts (and their tests) build the
 * same `MenuRequestContext` the bundle hands to its ports instead of calling the constructor by hand.
 */
final class MenuRequestContextFactoryPublicApiTest extends TestCase
{
    public function testFromRequestIsTheOnlyPublicEntryPoint(): void
    {
        $class = new ReflectionClass(MenuRequestContextFactory::class);
        self::assertStringNotContainsString('@internal', (string) $class->getDocComment(), 'The class is no longer internal.');

        $method = $class->getMethod('fromRequest');
        self::assertTrue($method->isPublic() && $method->isStatic());
        self::assertStringNotContainsString('@internal', (string) $method->getDocComment());
        self::assertCount(1, $method->getParameters());
        $parameter = $method->getParameters()[0]->getType();
        $return    = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $parameter);
        self::assertInstanceOf(ReflectionNamedType::class, $return);
        self::assertSame([Request::class, MenuRequestContext::class], [$parameter->getName(), $return->getName()]);

        foreach (['droppedValues', 'permissionContext'] as $name) {
            self::assertStringContainsString('@internal', (string) $class->getMethod($name)->getDocComment(), $name);
        }
    }

    public function testFromRequestBuildsWhatAHostUsedToBuildByHand(): void
    {
        $request = Request::create('https://app.example.test/es/organizations/acme/settings?tab=members');
        $request->attributes->set('_route', 'org_settings');
        $request->attributes->set('_route_params', ['slug' => 'acme', 'list' => ['x'], 7 => 'numeric']);
        $request->setLocale('es');

        // what a host test wrote by hand before 2.20 (route, route params, path, locale, query, host)
        $byHand = new MenuRequestContext('org_settings', ['slug' => 'acme'], '/es/organizations/acme/settings', 'es', ['tab' => 'members'], 'app.example.test');

        self::assertEquals($byHand, MenuRequestContextFactory::fromRequest($request), 'The same context as 2.19 (unchanged behaviour).');
        self::assertEquals(new MenuRequestContext(null, [], '/', 'en', [], 'localhost'), MenuRequestContextFactory::fromRequest(Request::create('/')));
        self::assertNotSame(MenuRequestContextFactory::fromRequest($request), MenuRequestContextFactory::fromRequest($request), 'Built per call, nothing memoised.');
    }
}
