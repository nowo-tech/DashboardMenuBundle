<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\CurrentRouteTreeDecorator;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CurrentRouteTreeDecoratorTest extends TestCase
{
    public function testDecorateMarksCurrentItemAndBranch(): void
    {
        $root = new MenuItem();
        $root->setLabel('Root');

        $childCurrent = new MenuItem();
        $childCurrent->setLabel('Current');
        $childCurrent->setItemType(MenuItem::ITEM_TYPE_LINK);
        $childCurrent->setRouteName('app_home');

        $childOther = new MenuItem();
        $childOther->setLabel('Other');
        $childOther->setItemType(MenuItem::ITEM_TYPE_LINK);
        $childOther->setRouteName('app_other');

        $tree = [
            [
                'item'     => $root,
                'children' => [
                    ['item' => $childCurrent, 'children' => []],
                    ['item' => $childOther, 'children' => []],
                ],
            ],
        ];

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(static function (string $routeName, array $params, int $referenceType): string {
                self::assertSame(UrlGeneratorInterface::ABSOLUTE_PATH, $referenceType);

                return match ($routeName) {
                    'app_home'  => '/status?view=overview',
                    'app_other' => '/status?view=other',
                    default     => '#',
                };
            });
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);

        $decorator = new CurrentRouteTreeDecorator($urlResolver);

        $request = Request::create('/status', 'GET', ['view' => 'overview']);
        $result  = $decorator->decorate($tree, $request);

        self::assertCount(1, $result);
        $rootNode = $result[0];
        self::assertTrue($rootNode['hasCurrentInBranch']);
        self::assertFalse($rootNode['isCurrent']);

        $children = $rootNode['children'];
        self::assertCount(2, $children);
        self::assertTrue($children[0]['isCurrent']);
        self::assertTrue($children[0]['hasCurrentInBranch']);
        self::assertFalse($children[1]['isCurrent']);
        self::assertFalse($children[1]['hasCurrentInBranch']);
    }

    public function testIsLinkCurrentReturnsFalseForNonLinkItemType(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SECTION);
        $item->setLabel('Section');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/page');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $ref    = new ReflectionClass(CurrentRouteTreeDecorator::class);
        $method = $ref->getMethod('isLinkCurrent');

        self::assertFalse($method->invoke($decorator, $item, '/page', []));
    }

    public function testIsLinkCurrentHandlesEmptyPath(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_home');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $ref    = new ReflectionClass(CurrentRouteTreeDecorator::class);
        $method = $ref->getMethod('isLinkCurrent');

        self::assertFalse($method->invoke($decorator, $item, '/status', []));
    }

    public function testIsLinkCurrentHandlesHashPath(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_other');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('#');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $ref    = new ReflectionClass(CurrentRouteTreeDecorator::class);
        $method = $ref->getMethod('isLinkCurrent');

        self::assertFalse($method->invoke($decorator, $item, '/status', []));
    }

    public function testNormalizePathVariants(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('#');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $ref    = new ReflectionClass(CurrentRouteTreeDecorator::class);
        $method = $ref->getMethod('normalizePath');

        self::assertSame('', $method->invoke($decorator, ''));
        self::assertSame('#', $method->invoke($decorator, '#'));
        self::assertSame('/', $method->invoke($decorator, '/'));
        self::assertSame('/status', $method->invoke($decorator, '/status/'));
        self::assertSame('/status', $method->invoke($decorator, ' /status '));
    }

    public function testParseQueryFromUrlHandlesNoQueryAndArrays(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('#');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $ref    = new ReflectionClass(CurrentRouteTreeDecorator::class);
        $method = $ref->getMethod('parseQueryFromUrl');

        self::assertSame([], $method->invoke($decorator, '/status'));
        self::assertSame(['view' => 'overview'], $method->invoke($decorator, '/status?view=overview'));

        $parsed = $method->invoke($decorator, '/status?tags[]=a&tags[]=b');
        self::assertSame(['a', 'b'], $parsed['tags']);

        $invalidUrl    = '://';
        $parsedInvalid = $method->invoke($decorator, $invalidUrl);
        self::assertSame([], $parsedInvalid);
    }

    public function testAnyChildHasCurrentInBranch(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('#');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $ref    = new ReflectionClass(CurrentRouteTreeDecorator::class);
        $method = $ref->getMethod('anyChildHasCurrentInBranch');

        self::assertFalse($method->invoke($decorator, []));
        self::assertFalse($method->invoke($decorator, [['hasCurrentInBranch' => false]]));
        self::assertTrue($method->invoke($decorator, [['hasCurrentInBranch' => false], ['hasCurrentInBranch' => true]]));
    }

    public function testIsLinkCurrentReturnsFalseWhenLinkQueryParamMissingInRequest(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_page');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/page?foo=1');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $tree    = [['item' => $item, 'children' => []]];
        $request = Request::create('/page', 'GET', []);
        $result  = $decorator->decorate($tree, $request);

        self::assertFalse($result[0]['isCurrent']);
    }

    /**
     * Covers the branch when path does not match (linkPath !== normalizedCurrentPath, line 96).
     */
    public function testIsLinkCurrentReturnsFalseWhenPathDoesNotMatch(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_other');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/other-page');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $tree    = [['item' => $item, 'children' => []]];
        $request = Request::create('/current-page', 'GET');
        $result  = $decorator->decorate($tree, $request);

        self::assertFalse($result[0]['isCurrent']);
    }

    public function testIsLinkCurrentReturnsFalseWhenQueryParamValueDiffers(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_page');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/page?view=overview');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $tree    = [['item' => $item, 'children' => []]];
        $request = Request::create('/page', 'GET', ['view' => 'other']);
        $result  = $decorator->decorate($tree, $request);

        self::assertFalse($result[0]['isCurrent']);
    }

    public function testIsLinkCurrentReturnsTrueWhenPathAndQueryMatch(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_page');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/page?view=overview');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $tree    = [['item' => $item, 'children' => []]];
        $request = Request::create('/page', 'GET', ['view' => 'overview']);
        $result  = $decorator->decorate($tree, $request);

        self::assertTrue($result[0]['isCurrent']);
    }

    /**
     * Covers the path where path matches and link has no query params (foreach never runs, return true at line 116).
     */
    public function testIsLinkCurrentReturnsTrueWhenPathMatchesAndLinkHasNoQueryParams(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_page');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/page');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $tree    = [['item' => $item, 'children' => []]];
        $request = Request::create('/page', 'GET');
        $result  = $decorator->decorate($tree, $request);

        self::assertTrue($result[0]['isCurrent']);
    }

    public function testIsLinkCurrentReturnsFalseWhenLinkValueIsArrayButCurrentIsNot(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_page');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/page?tags[]=a&tags[]=b');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $tree    = [['item' => $item, 'children' => []]];
        $request = Request::create('/page', 'GET', ['tags' => 'a']);
        $result  = $decorator->decorate($tree, $request);

        self::assertFalse($result[0]['isCurrent']);
    }

    public function testIsLinkCurrentReturnsFalseWhenBothArraysButNotEqual(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_page');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/page?tags[]=a&tags[]=b');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $tree    = [['item' => $item, 'children' => []]];
        $request = Request::create('/page', 'GET', ['tags' => ['a', 'c']]);
        $result  = $decorator->decorate($tree, $request);

        self::assertFalse($result[0]['isCurrent']);
    }

    public function testIsLinkCurrentReturnsTrueWhenBothArraysAndEqual(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_page');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/page?tags[]=a&tags[]=b');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $tree    = [['item' => $item, 'children' => []]];
        $request = Request::create('/page', 'GET', ['tags' => ['a', 'b']]);
        $result  = $decorator->decorate($tree, $request);

        self::assertTrue($result[0]['isCurrent']);
    }

    public function testNormalizePathReturnsEmptyWhenParseUrlPathFails(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('#');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $ref    = new ReflectionClass(CurrentRouteTreeDecorator::class);
        $method = $ref->getMethod('normalizePath');

        self::assertSame('', $method->invoke($decorator, ':'));
    }

    /**
     * Covers the branch in isLinkCurrent where one of link/current value is array and the other is not,
     * or both are arrays but not equal (inner return false at line 96).
     */
    public function testIsLinkCurrentReturnsFalseWhenOneValueIsArrayAndOtherIsNot(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_page');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/page?tags[]=a&tags[]=b');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $ref    = new ReflectionClass(CurrentRouteTreeDecorator::class);
        $method = $ref->getMethod('isLinkCurrent');

        $normalizedPath = '/page';
        $currentQuery   = ['tags' => 'scalar'];
        self::assertFalse($method->invoke($decorator, $item, $normalizedPath, $currentQuery));
    }

    /**
     * Covers the branch where both link and current values are arrays but not equal.
     */
    public function testIsLinkCurrentReturnsFalseWhenBothArraysButValuesDiffer(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName('app_page');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/page?tags[]=a&tags[]=b');
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);
        $decorator   = new CurrentRouteTreeDecorator($urlResolver);

        $ref    = new ReflectionClass(CurrentRouteTreeDecorator::class);
        $method = $ref->getMethod('isLinkCurrent');

        $normalizedPath = '/page';
        $currentQuery   = ['tags' => ['a', 'c']];
        self::assertFalse($method->invoke($decorator, $item, $normalizedPath, $currentQuery));
    }
}
