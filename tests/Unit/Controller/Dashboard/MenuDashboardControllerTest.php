<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Controller\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Controller\Dashboard\MenuDashboardController;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\ImportExportRateLimiter;
use Nowo\DashboardMenuBundle\Service\MenuExporter;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;

final class MenuDashboardControllerTest extends TestCase
{
    public function testGetDashboardRoutesReturnsExpectedKeys(): void
    {
        $controller = $this->createController();
        $routes     = $this->invokePrivate($controller, 'getDashboardRoutes');

        self::assertSame(MenuDashboardController::ROUTE_INDEX, $routes['index']);
        self::assertSame(MenuDashboardController::ROUTE_SHOW, $routes['show']);
        self::assertSame(MenuDashboardController::ROUTE_MENU_NEW, $routes['menu_new']);
        self::assertSame(MenuDashboardController::ROUTE_ITEM_DELETE, $routes['item_delete']);
    }

    public function testGetModalClassesMapsSizesToBootstrapClasses(): void
    {
        $controller = $this->createController(modalSizes: [
            'menu_form' => 'lg',
            'copy'      => 'normal',
            'item_form' => 'xl',
            'delete'    => 'normal',
        ]);
        $classes = $this->invokePrivate($controller, 'getModalClasses');

        self::assertSame('modal-lg', $classes['menu_form']);
        self::assertSame('', $classes['copy']);
        self::assertSame('modal-xl', $classes['item_form']);
        self::assertSame('', $classes['delete']);
    }

    public function testGetModalClassesUsesDefaultsWhenKeysMissing(): void
    {
        $controller = $this->createController(modalSizes: []);
        $classes    = $this->invokePrivate($controller, 'getModalClasses');

        self::assertSame('', $classes['menu_form']);
        self::assertSame('modal-lg', $classes['item_form']);
    }

    public function testGetModalClassesMapsXlToModalXl(): void
    {
        $controller = $this->createController(modalSizes: ['item_form' => 'xl']);
        $classes    = $this->invokePrivate($controller, 'getModalClasses');
        self::assertSame('modal-xl', $classes['item_form']);
    }

    public function testComputeItemDepths(): void
    {
        $root = new MenuItem();
        $root->setLabel('Root');
        $this->setId($root, 1);
        $child = new MenuItem();
        $child->setLabel('Child');
        $child->setParent($root);
        $this->setId($child, 2);

        $controller = $this->createController();
        $depths     = $this->invokePrivate($controller, 'computeItemDepths', [[$root, $child]]);

        self::assertSame([1 => 0, 2 => 1], $depths);
    }

    public function testComputeItemDepthsSkipsNullId(): void
    {
        $item = new MenuItem();
        $item->setLabel('NoId');
        $controller = $this->createController();
        $depths     = $this->invokePrivate($controller, 'computeItemDepths', [[$item]]);

        self::assertSame([], $depths);
    }

    public function testComputeSiblingMaps(): void
    {
        $a = new MenuItem();
        $this->setId($a, 1);
        $b = new MenuItem();
        $this->setId($b, 2);
        $c = new MenuItem();
        $this->setId($c, 3);

        $controller = $this->createController();
        $result     = $this->invokePrivate($controller, 'computeSiblingMaps', [[$a, $b, $c]]);

        self::assertNull($result['prev'][1]);
        self::assertSame(2, $result['next'][1]);
        self::assertSame(1, $result['prev'][2]);
        self::assertSame(3, $result['next'][2]);
        self::assertSame(2, $result['prev'][3]);
        self::assertNull($result['next'][3]);
    }

    public function testComputeParentLabels(): void
    {
        $root = new MenuItem();
        $root->setLabel('Root');
        $this->setId($root, 1);
        $child = new MenuItem();
        $child->setParent($root);
        $this->setId($child, 2);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->with('dashboard.root')->willReturn('— Raíz —');
        $controller = $this->createController(translator: $translator);
        $labels     = $this->invokePrivate($controller, 'computeParentLabels', [[$root, $child], 'en']);

        self::assertSame('— Raíz —', $labels[1]);
        self::assertSame('Root', $labels[2]);
    }

    public function testComputeParentLabelsSkipsNullId(): void
    {
        $item = new MenuItem();
        $item->setLabel('NoId');
        $translator = $this->createStub(TranslatorInterface::class);
        $controller = $this->createController(translator: $translator);
        $labels     = $this->invokePrivate($controller, 'computeParentLabels', [[$item], 'en']);
        self::assertSame([], $labels);
    }

    public function testIsRouteNameExcludedReturnsTrueWhenPatternMatches(): void
    {
        $controller = $this->createController(routeNameExcludePatterns: ['#^_#', '#^web_profiler#']);
        self::assertTrue($this->invokePrivate($controller, 'isRouteNameExcluded', ['_internal']));
        self::assertTrue($this->invokePrivate($controller, 'isRouteNameExcluded', ['web_profiler_main']));
    }

    public function testIsRouteNameExcludedReturnsFalseWhenNoPatternMatches(): void
    {
        $controller = $this->createController(routeNameExcludePatterns: ['#^_#']);
        self::assertFalse($this->invokePrivate($controller, 'isRouteNameExcluded', ['app_home']));
    }

    public function testGetAppRoutesForItemAddsCurrentRouteWhenMissing(): void
    {
        $item = new MenuItem();
        $item->setRouteName('my_custom_route');
        $controller = $this->createController();
        $appRoutes  = ['app_home' => ['label' => 'Home', 'params' => []]];
        $result     = $this->invokePrivate($controller, 'getAppRoutesForItem', [$item, $appRoutes]);

        self::assertArrayHasKey('my_custom_route', $result);
        self::assertSame('my_custom_route (current)', $result['my_custom_route']['label']);
    }

    public function testGetAppRoutesForItemDoesNotAddWhenRouteExists(): void
    {
        $item = new MenuItem();
        $item->setRouteName('app_home');
        $controller = $this->createController();
        $appRoutes  = ['app_home' => ['label' => 'Home', 'params' => []]];
        $result     = $this->invokePrivate($controller, 'getAppRoutesForItem', [$item, $appRoutes]);

        self::assertCount(1, $result);
        self::assertSame('Home', $result['app_home']['label']);
    }

    public function testGetDescendantIds(): void
    {
        $root = new MenuItem();
        $this->setId($root, 1);
        $child = new MenuItem();
        $this->setId($child, 2);
        $child->setParent($root);
        $root->getChildren()->add($child);

        $controller = $this->createController();
        $ids        = $this->invokePrivate($controller, 'getDescendantIds', [$root]);

        self::assertSame([1, 2], $ids);
    }

    public function testIndexWithPaginationEnabled(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('countForDashboard')->willReturn(0);
        $menuRepo->method('findForDashboard')->willReturn([]);

        $controller = $this->createController(
            menuRepository: $menuRepo,
            paginationEnabled: true,
            paginationPerPage: 10,
        );
        $this->setControllerContainer($controller);

        $request  = Request::create('/');
        $response = $controller->index($request);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithPaginationDisabled(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findForDashboard')->with('', 0)->willReturn([]);

        $controller = $this->createController(menuRepository: $menuRepo, paginationEnabled: false);
        $this->setControllerContainer($controller);

        $request  = Request::create('/');
        $response = $controller->index($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testIndexWithPaginationAndTotalPagesCapsPage(): void
    {
        $menu = new Menu();
        $menu->setCode('one');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('countForDashboard')->with('')->willReturn(5);
        $menuRepo->method('findForDashboard')->willReturnCallback(static function (string $search, int $offset, int $limit) use ($menu): array {
            self::assertLessThanOrEqual(2, $limit);

            return $offset === 0 ? [$menu] : [];
        });

        $controller = $this->createController(
            menuRepository: $menuRepo,
            paginationEnabled: true,
            paginationPerPage: 2,
        );
        $this->setControllerContainer($controller);

        $request  = Request::create('/?page=99');
        $response = $controller->index($request);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testShowThrowsWhenMenuNotFound(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(999)->willReturn(null);

        $controller = $this->createController(menuRepository: $menuRepo);
        $this->setControllerContainer($controller);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->show(999);
    }

    public function testShowRendersWhenMenuFound(): void
    {
        $menu = new Menu();
        $menu->setCode('nav');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);

        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);

        $response = $controller->show(1);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testDeleteMenuThrowsWhenMenuNotFound(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(999)->willReturn(null);

        $controller = $this->createController(menuRepository: $menuRepo);
        $this->setControllerContainer($controller);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->deleteMenu($this->createPostRequestWithCsrf(), 999);
    }

    public function testDeleteMenuRemovesAndRedirects(): void
    {
        $menu = new Menu();
        $menu->setCode('del');
        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($menu, 1);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($menu);
        $em->expects(self::once())->method('flush');

        $controller = $this->createController(menuRepository: $menuRepo, entityManager: $em);
        $this->setControllerContainer($controller);

        $response = $controller->deleteMenu($this->createPostRequestWithCsrf(), 1);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testItemMoveUpWhenAlreadyFirstRedirectsWithInfo(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $item = new MenuItem();
        $item->setMenu($menu);
        $this->setId($item, 10);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(10)->willReturn($item);
        $itemRepo->method('findSiblingsByPosition')->with($item)->willReturn([$item]);

        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);

        $response = $controller->itemMoveUp($this->createPostRequestWithCsrf(), 1, 10);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function testItemMoveDownWhenAlreadyLastRedirectsWithInfo(): void
    {
        $menu = new Menu();
        $item = new MenuItem();
        $item->setMenu($menu);
        $this->setId($item, 10);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(10)->willReturn($item);
        $itemRepo->method('findSiblingsByPosition')->with($item)->willReturn([$item]);

        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);

        $response = $controller->itemMoveDown($this->createPostRequestWithCsrf(), 1, 10);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function testItemMoveUpThrowsWhenMenuNotFound(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(999)->willReturn(null);

        $controller = $this->createController(menuRepository: $menuRepo);
        $this->setControllerContainer($controller);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->itemMoveUp($this->createPostRequestWithCsrf(), 999, 1);
    }

    public function testItemMoveUpWhenItemNotFoundRedirectsWithError(): void
    {
        $menu     = new Menu();
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(999)->willReturn(null);

        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);

        $response = $controller->itemMoveUp($this->createPostRequestWithCsrf(), 1, 999);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function testItemMoveDownThrowsWhenMenuNotFound(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(999)->willReturn(null);

        $controller = $this->createController(menuRepository: $menuRepo);
        $this->setControllerContainer($controller);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->itemMoveDown($this->createPostRequestWithCsrf(), 999, 1);
    }

    public function testItemMoveUpWhenItemBelongsToOtherMenuRedirectsWithError(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $otherMenu = new Menu();
        $otherMenu->setCode('other');
        $item = new MenuItem();
        $item->setMenu($otherMenu);
        $this->setId($item, 10);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(10)->willReturn($item);

        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);

        $response = $controller->itemMoveUp($this->createPostRequestWithCsrf(), 1, 10);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function testItemMoveDownWhenItemBelongsToOtherMenuRedirectsWithError(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $otherMenu = new Menu();
        $item      = new MenuItem();
        $item->setMenu($otherMenu);
        $this->setId($item, 10);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(10)->willReturn($item);

        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);

        $response = $controller->itemMoveDown($this->createPostRequestWithCsrf(), 1, 10);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function testEditMenuThrowsWhenMenuNotFound(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(999)->willReturn(null);

        $controller = $this->createController(menuRepository: $menuRepo);
        $this->setControllerContainer($controller);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->editMenu(Request::create('/999/edit'), 999);
    }

    public function testEditMenuRendersFormOnGet(): void
    {
        $menu = new Menu();
        $menu->setCode('edit');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);

        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);

        $response = $controller->editMenu(Request::create('/1/edit'), 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testNewMenuRendersFormOnGet(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('countForDashboard')->willReturn(0);
        $menuRepo->method('findForDashboard')->willReturn([]);
        $controller = $this->createController(menuRepository: $menuRepo, paginationEnabled: true);
        $this->setControllerContainer($controller);
        $response = $controller->newMenu(Request::create('/menu/new'));
        self::assertSame(200, $response->getStatusCode());
    }

    public function testNewMenuSubmittedWithEmptyCodeAddsFlashAndRenders(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('countForDashboard')->willReturn(0);
        $menuRepo->method('findForDashboard')->willReturn([]);
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $controller = $this->createController(menuRepository: $menuRepo, paginationEnabled: true);
        $this->setControllerContainer($controller, $form);
        $response = $controller->newMenu(Request::create('/menu/new', 'POST'));
        self::assertSame(200, $response->getStatusCode());
    }

    public function testNewMenuSubmittedValidWithNewCodePersistsAndRedirects(): void
    {
        $menu = new Menu();
        $menu->setCode('newcode');
        $menu->setContext([]);
        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($menu, 1);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('countForDashboard')->willReturn(0);
        $menuRepo->method('findForDashboard')->willReturn([]);
        $menuRepo->method('findOneByCodeAndContext')->with('newcode', self::anything())->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(static fn ($m): bool => $m instanceof Menu && $m->getCode() === 'newcode'));
        $em->expects(self::once())->method('flush');

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($menu);

        $controller = $this->createController(menuRepository: $menuRepo, entityManager: $em, paginationEnabled: true);
        $this->setControllerContainer($controller, $form);

        $response = $controller->newMenu(Request::create('/menu/new', 'POST'));
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testNewMenuSubmittedValidWithDuplicateCodeAddsFormErrorAndRenders(): void
    {
        $menu = new Menu();
        $menu->setCode('dup');
        $menu->setContext([]);
        $existing = new Menu();
        $existing->setCode('dup');
        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($existing, 2);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('countForDashboard')->willReturn(0);
        $menuRepo->method('findForDashboard')->willReturn([]);
        $menuRepo->method('findOneByCodeAndContext')->with('dup', self::anything())->willReturn($existing);

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn($menu);
        $form->method('addError')->willReturnSelf();

        $controller = $this->createController(menuRepository: $menuRepo, paginationEnabled: true);
        $this->setControllerContainer($controller, $form);

        $response = $controller->newMenu(Request::create('/menu/new', 'POST'));
        self::assertSame(200, $response->getStatusCode());
    }

    public function testNewMenuWithPartialQueryRendersPartial(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('countForDashboard')->willReturn(0);
        $menuRepo->method('findForDashboard')->willReturn([]);
        $controller = $this->createController(menuRepository: $menuRepo, paginationEnabled: true);
        $this->setControllerContainer($controller);
        $response = $controller->newMenu(Request::create('/menu/new?_partial=1'));
        self::assertSame(200, $response->getStatusCode());
    }

    public function testEditMenuWithPartialQueryRendersPartial(): void
    {
        $menu = new Menu();
        $menu->setCode('edit');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);
        $response = $controller->editMenu(Request::create('/1/edit?_partial=1'), 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testEditMenuSubmittedValidFlushesAndRedirects(): void
    {
        $menu = new Menu();
        $menu->setCode('mine');
        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($menu, 1);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $menuRepo->method('findOneByCodeAndContext')->with('mine', self::anything())->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, entityManager: $em);
        $this->setControllerContainer($controller, $form);
        $response = $controller->editMenu(Request::create('/1/edit', 'POST'), 1);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testEditMenuSubmittedWithDuplicateCodeAddsErrorAndRenders(): void
    {
        $menu = new Menu();
        $menu->setCode('dup');
        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($menu, 1);
        $other = new Menu();
        $other->setCode('dup');
        $ref->setValue($other, 2);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $menuRepo->method('findOneByCodeAndContext')->with('dup', self::anything())->willReturn($other);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('addError')->willReturnSelf();
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller, $form);
        $response = $controller->editMenu(Request::create('/1/edit', 'POST'), 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testEditMenuWhenBaseRestoresOriginalCodeBeforeCheck(): void
    {
        $menu = new Menu();
        $menu->setCode('original');
        $menu->setBase(true);
        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($menu, 1);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $menuRepo->method('findOneByCodeAndContext')->with('original', self::anything())->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, entityManager: $em);
        $this->setControllerContainer($controller, $form);
        $response = $controller->editMenu(Request::create('/1/edit', 'POST'), 1);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('original', $menu->getCode());
    }

    public function testCopyMenuSubmittedWithDuplicateCodeRendersFormWithError(): void
    {
        $menu = new Menu();
        $menu->setCode('orig');
        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($menu, 1);
        $existing = new Menu();
        $existing->setCode('dup');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $menuRepo->method('findOneByCodeAndContext')->with('dup', self::anything())->willReturn($existing);
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['code' => 'dup', 'name' => 'Copy']);
        $controller = $this->createController(menuRepository: $menuRepo);
        $this->setControllerContainer($controller, $form);
        $response = $controller->copyMenu(Request::create('/1/copy', 'POST'), 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testCopyMenuWithPartialQueryRendersPartial(): void
    {
        $menu = new Menu();
        $menu->setCode('orig');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $controller = $this->createController(menuRepository: $menuRepo);
        $this->setControllerContainer($controller);
        $response = $controller->copyMenu(Request::create('/1/copy?_partial=1'), 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testNewItemWithValidParentIdSetsParent(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $parent = new MenuItem();
        $parent->setMenu($menu);
        $this->setId($parent, 10);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(10)->willReturn($parent);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, router: $router);
        $this->setControllerContainer($controller);
        $request  = Request::create('/1/item/new?parent=10');
        $response = $controller->newItem($request, 1);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * Covers getAppRoutes() loop: route collection with routes (including path with params and excluded name).
     */
    public function testNewItemWithRouterContainingRoutesBuildsAppRoutes(): void
    {
        $collection = new RouteCollection();
        $collection->add('app_show', new Route('/show/{id}'));
        $collection->add('_internal', new Route('/internal'));
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);
        $router->method('generate')->willReturn('/generated');

        $menu = new Menu();
        $menu->setCode('m');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);

        $controller = $this->createController(
            menuRepository: $menuRepo,
            menuItemRepository: $itemRepo,
            router: $router,
            routeNameExcludePatterns: ['#^_#'],
        );
        $this->setControllerContainer($controller);
        $response = $controller->newItem(Request::create('/1/item/new'), 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testItemMoveUpSuccessSwapsAndRedirects(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $first = new MenuItem();
        $first->setMenu($menu);
        $first->setPosition(0);
        $this->setId($first, 1);
        $second = new MenuItem();
        $second->setMenu($menu);
        $second->setPosition(1);
        $this->setId($second, 2);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(2)->willReturn($second);
        $itemRepo->method('findSiblingsByPosition')->with($second)->willReturn([$first, $second]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, entityManager: $em);
        $this->setControllerContainer($controller);
        $response = $controller->itemMoveUp($this->createPostRequestWithCsrf(), 1, 2);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testItemMoveDownSuccessSwapsAndRedirects(): void
    {
        $menu  = new Menu();
        $first = new MenuItem();
        $first->setMenu($menu);
        $first->setPosition(0);
        $this->setId($first, 1);
        $second = new MenuItem();
        $second->setMenu($menu);
        $second->setPosition(1);
        $this->setId($second, 2);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(1)->willReturn($first);
        $itemRepo->method('findSiblingsByPosition')->with($first)->willReturn([$first, $second]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, entityManager: $em);
        $this->setControllerContainer($controller);
        $response = $controller->itemMoveDown($this->createPostRequestWithCsrf(), 1, 1);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testCopyMenuThrowsWhenMenuNotFound(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(999)->willReturn(null);

        $controller = $this->createController(menuRepository: $menuRepo);
        $this->setControllerContainer($controller);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->copyMenu(Request::create('/999/copy'), 999);
    }

    public function testCopyMenuRendersFormOnGet(): void
    {
        $menu = new Menu();
        $menu->setCode('orig');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $controller = $this->createController(menuRepository: $menuRepo);
        $this->setControllerContainer($controller);
        $response = $controller->copyMenu(Request::create('/1/copy'), 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testCopyMenuSubmittedValidWithNewCodeClonesMenuAndRedirects(): void
    {
        $menu = new Menu();
        $menu->setCode('orig');
        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($menu, 1);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $menuRepo->method('findOneByCodeAndContext')->with('newcode', self::anything())->willReturn(null);

        $rootItem = new MenuItem();
        $rootItem->setMenu($menu);
        $rootItem->setPosition(0);
        $rootItem->setLabel('Root');
        $this->setId($rootItem, 10);
        $childItem = new MenuItem();
        $childItem->setMenu($menu);
        $childItem->setParent($rootItem);
        $childItem->setPosition(0);
        $childItem->setLabel('Child');
        $this->setId($childItem, 11);
        $rootItem->getChildren()->add($childItem);

        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([$rootItem, $childItem]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function ($entity): void {
            if ($entity instanceof Menu && $entity->getCode() === 'newcode') {
                $r = new ReflectionProperty(Menu::class, 'id');
                $r->setValue($entity, 99);
            }
        });
        $em->expects(self::atLeast(2))->method('flush');

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['code' => 'newcode', 'name' => 'Copy of orig']);

        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, entityManager: $em);
        $this->setControllerContainer($controller, $form);

        $response = $controller->copyMenu(Request::create('/1/copy', 'POST'), 1);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testNewItemRendersFormOnGet(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, router: $router);
        $this->setControllerContainer($controller);
        $response = $controller->newItem(Request::create('/1/item/new'), 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testNewItemThrowsWhenMenuNotFound(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(999)->willReturn(null);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $router   = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, router: $router);
        $this->setControllerContainer($controller);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->newItem(Request::create('/999/item/new'), 999);
    }

    public function testNewItemSubmittedValidPersistsAndRedirects(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(static fn ($e): bool => $e instanceof MenuItem));
        $em->expects(self::once())->method('flush');
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, entityManager: $em, router: $router);
        $this->setControllerContainer($controller, $form);
        $response = $controller->newItem(Request::create('/1/item/new', 'POST'), 1);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testNewItemWithPartialQueryRendersPartial(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, router: $router);
        $this->setControllerContainer($controller);
        $response = $controller->newItem(Request::create('/1/item/new?_partial=1'), 1);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testEditItemRendersFormOnGet(): void
    {
        $menu = new Menu();
        $item = new MenuItem();
        $item->setMenu($menu);
        $this->setId($item, 5);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(5)->willReturn($item);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, router: $router);
        $this->setControllerContainer($controller);
        $response = $controller->editItem(Request::create('/1/item/5/edit'), 1, 5);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testEditItemSubmittedValidFlushesAndRedirects(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $item = new MenuItem();
        $item->setMenu($menu);
        $this->setId($item, 5);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(5)->willReturn($item);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, entityManager: $em, router: $router);
        $this->setControllerContainer($controller, $form);
        $response = $controller->editItem(Request::create('/1/item/5/edit', 'POST'), 1, 5);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testEditItemWithPartialQueryRendersPartial(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $item = new MenuItem();
        $item->setMenu($menu);
        $this->setId($item, 5);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(5)->willReturn($item);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([]);
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, router: $router);
        $this->setControllerContainer($controller);
        $response = $controller->editItem(Request::create('/1/item/5/edit?_partial=1'), 1, 5);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testEditItemRedirectsWhenItemBelongsToOtherMenu(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $otherMenu = new Menu();
        $item      = new MenuItem();
        $item->setMenu($otherMenu);
        $this->setId($item, 5);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(5)->willReturn($item);
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, router: $router);
        $this->setControllerContainer($controller);
        $response = $controller->editItem(Request::create('/1/item/5/edit'), 1, 5);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function testEditItemThrowsWhenMenuNotFound(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(999)->willReturn(null);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $router   = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, router: $router);
        $this->setControllerContainer($controller);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->editItem(Request::create('/999/item/1/edit'), 999, 1);
    }

    public function testDeleteItemThrowsWhenMenuNotFound(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(999)->willReturn(null);
        $itemRepo   = $this->createStub(MenuItemRepository::class);
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->deleteItem($this->createPostRequestWithCsrf(), 999, 1);
    }

    public function testDeleteItemRedirectsWhenItemNotFound(): void
    {
        $menu     = new Menu();
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(999)->willReturn(null);
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);
        $response = $controller->deleteItem($this->createPostRequestWithCsrf(), 1, 999);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
    }

    public function testDeleteItemRemovesAndRedirectsWhenFound(): void
    {
        $menu = new Menu();
        $menu->setCode('m');
        $item = new MenuItem();
        $item->setMenu($menu);
        $this->setId($item, 5);
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(1)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('find')->with(5)->willReturn($item);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($item);
        $em->expects(self::once())->method('flush');
        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo, entityManager: $em);
        $this->setControllerContainer($controller);
        $response = $controller->deleteItem($this->createPostRequestWithCsrf(), 1, 5);
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testExportAllReturnsJsonStreamWithAttachmentHeaders(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findAll')->willReturn([]);
        $itemRepo   = $this->createStub(MenuItemRepository::class);
        $em         = $this->createStub(EntityManagerInterface::class);
        $router     = $this->createStub(RouterInterface::class);
        $translator = $this->createStub(TranslatorInterface::class);

        $controller = $this->createController(
            menuRepository: $menuRepo,
            menuItemRepository: $itemRepo,
            entityManager: $em,
            router: $router,
            translator: $translator,
        );
        $this->setControllerContainer($controller);

        $request  = Request::create('/dashboard/menu/export');
        $response = $controller->exportAll($request);

        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function testExportMenuReturnsJsonStreamWithSafeFilename(): void
    {
        $menu = new Menu();
        $menu->setCode('weird code!');
        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($menu, 123);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneById')->with(123)->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTreeForExport')->with($menu)->willReturn([]);

        $controller = $this->createController(menuRepository: $menuRepo, menuItemRepository: $itemRepo);
        $this->setControllerContainer($controller);

        $request  = Request::create('/dashboard/menu/123/export');
        $response = $controller->exportMenu($request, 123);

        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('menu-weird_code_-export.json', (string) $response->headers->get('Content-Disposition'));
    }

    private function createController(
        ?MenuRepository $menuRepository = null,
        ?MenuItemRepository $menuItemRepository = null,
        ?EntityManagerInterface $entityManager = null,
        ?RouterInterface $router = null,
        ?TranslatorInterface $translator = null,
        ?MenuExporter $menuExporter = null,
        ?MenuImporter $menuImporter = null,
        array $routeNameExcludePatterns = [],
        array $locales = [],
        bool $paginationEnabled = true,
        int $paginationPerPage = 20,
        array $modalSizes = [],
        ?string $iconSelectorScriptUrl = null,
        int $importMaxBytes = 2_097_152,
        ?ImportExportRateLimiter $importExportRateLimiter = null,
    ): MenuDashboardController {
        $menuRepo    = $menuRepository ?? $this->createStub(MenuRepository::class);
        $itemRepo    = $menuItemRepository ?? $this->createStub(MenuItemRepository::class);
        $em          = $entityManager ?? $this->createStub(EntityManagerInterface::class);
        $exporter    = $menuExporter ?? new MenuExporter($menuRepo, $itemRepo);
        $importer    = $menuImporter ?? new MenuImporter($menuRepo, $em);
        $rateLimiter = $importExportRateLimiter ?? new ImportExportRateLimiter(null, 0, 60);

        return new MenuDashboardController(
            $menuRepo,
            $itemRepo,
            $em,
            $router ?? $this->createStub(RouterInterface::class),
            $translator ?? $this->createStub(TranslatorInterface::class),
            $exporter,
            $importer,
            $routeNameExcludePatterns,
            $locales,
            $paginationEnabled,
            $paginationPerPage,
            $modalSizes,
            $iconSelectorScriptUrl,
            $importMaxBytes,
            $rateLimiter,
        );
    }

    private function setControllerContainer(MenuDashboardController $controller, ?FormInterface $form = null): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $router->method('generate')->willReturn('/generated');

        $formToUse   = $form ?? $this->createDefaultFormStub();
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($formToUse);

        $session = new Session();

        $twig = $this->createStub(\Twig\Environment::class);
        $twig->method('render')->willReturn('<html></html>');

        $requestStack = new RequestStack();
        $request      = Request::create('/');
        $request->setSession($session);
        $requestStack->push($request);

        $csrfManager = $this->createStub(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn(true);

        $container = new class($router, $formFactory, $session, $twig, $requestStack, $csrfManager) implements \Psr\Container\ContainerInterface {
            public function __construct(
                private readonly RouterInterface $router,
                private readonly FormFactoryInterface $formFactory,
                private readonly Session $session,
                private readonly \Twig\Environment $twig,
                private readonly RequestStack $requestStack,
                private readonly \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $csrfTokenManager,
            ) {
            }

            public function get(string $id): mixed
            {
                return match ($id) {
                    'router'                      => $this->router,
                    'form.factory'                => $this->formFactory,
                    'session'                     => $this->session,
                    'twig'                        => $this->twig,
                    'request_stack'               => $this->requestStack,
                    'security.csrf.token_manager' => $this->csrfTokenManager,
                    default                       => throw new \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException($id),
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, ['router', 'form.factory', 'session', 'twig', 'request_stack', 'security.csrf.token_manager'], true);
            }
        };

        $controller->setContainer($container);
    }

    private function createDefaultFormStub(): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        return $form;
    }

    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        $ref = new ReflectionClass($object);
        $m   = $ref->getMethod($method);

        return $m->invoke($object, ...$args);
    }

    private function setId(MenuItem $item, int $id): void
    {
        $ref = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($item, $id);
    }

    private function createPostRequestWithCsrf(): Request
    {
        $request = Request::create('/', 'POST');
        $request->request->set('_token', 'test-csrf-token');

        return $request;
    }
}
