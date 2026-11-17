<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Controller\Dashboard;

use Nowo\DashboardMenuBundle\Controller\Dashboard\DashboardControllerTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * REQ-TEST-003: the trait's redirect helper, the single implementation MenuDashboardController uses (it no longer
 * declares a copy of its own).
 */
final class DashboardControllerTraitTest extends TestCase
{
    public function testOnlyASameHostRefererIsHonoured(): void
    {
        $controller = $this->controller();

        self::assertSame('http://localhost/menus?page=2', $controller->go($this->request('http://localhost/menus?page=2'))->getTargetUrl());
        self::assertSame('http://localhost/menus#items', $controller->go($this->request('http://localhost/menus#old'), 'items')->getTargetUrl());
        self::assertSame('http://localhost/menus', $controller->go($this->request('http://localhost/menus#old'), '')->getTargetUrl());
        self::assertSame('http://localhost/menus', $controller->go($this->request('http://localhost/menus'))->getTargetUrl());
        self::assertSame('/fallback', $controller->go($this->request('http://evil.example/x'))->getTargetUrl());
        self::assertSame('/fallback', $controller->go($this->request('/no-host'))->getTargetUrl());
        self::assertSame('/fallback', $controller->go($this->request(null))->getTargetUrl());
    }

    public function testWithoutAUsableRefererTheFragmentTravelsAsARouteParameter(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('generate')->with('some_route', ['id' => 3, '_fragment' => 'items'])->willReturn('/some/route#items');

        self::assertSame('/some/route#items', $this->controller($router)->go($this->request(null), 'items', ['id' => 3])->getTargetUrl());
    }

    private function request(?string $referer): Request
    {
        $request = Request::create('http://localhost/menus/1');
        if ($referer !== null) {
            $request->headers->set('Referer', $referer);
        }

        return $request;
    }

    private function controller(?RouterInterface $router = null): TraitProbeController
    {
        if (!$router instanceof RouterInterface) {
            $router = $this->createMock(RouterInterface::class);
            $router->method('generate')->willReturn('/fallback');
        }
        $controller = new TraitProbeController();
        $controller->setContainer(new ServiceLocator(['router' => static fn (): RouterInterface => $router]));

        return $controller;
    }
}

final class TraitProbeController extends AbstractController
{
    use DashboardControllerTrait;

    /**
     * @param array<string, mixed> $params
     */
    public function go(Request $request, ?string $fragment = null, array $params = []): RedirectResponse
    {
        return $this->redirectToRefererOr($request, 'some_route', $params, $fragment);
    }
}
