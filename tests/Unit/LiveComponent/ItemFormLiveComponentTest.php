<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\LiveComponent;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\MenuItemType;
use Nowo\DashboardMenuBundle\LiveComponent\ItemFormLiveComponent;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;

final class ItemFormLiveComponentTest extends TestCase
{
    private function setPrivateTraitProperty(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setValue($object, $value);
    }

    private function createEmptyFormView(): FormView
    {
        $view           = new FormView();
        $view->vars     = ['attr' => []];
        $view->children = [];

        return $view;
    }

    public function testGetItemTypePrefersFormValues(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $formMock  = $this->createMock(FormInterface::class);

        $formMock->method('getName')->willReturn('live_name');

        $data = new MenuItem();
        $data->setItemType(MenuItem::ITEM_TYPE_LINK);
        $formMock->method('getData')->willReturn($data);

        $this->setPrivateTraitProperty($component, 'form', $formMock);

        $component->formValues = [
            'live_name' => [
                'basic'  => ['itemType' => MenuItem::ITEM_TYPE_DIVIDER],
                'config' => [],
            ],
        ];

        self::assertSame(MenuItem::ITEM_TYPE_DIVIDER, $component->getItemType());
    }

    public function testGetItemTypeFallsBackToFormDataWhenUnset(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $formMock  = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');

        $data = new MenuItem();
        $data->setItemType(MenuItem::ITEM_TYPE_SECTION);
        $formMock->method('getData')->willReturn($data);

        $this->setPrivateTraitProperty($component, 'form', $formMock);
        $component->formValues = [];

        self::assertSame(MenuItem::ITEM_TYPE_SECTION, $component->getItemType());
    }

    public function testLinkFieldVisibilityDependsOnItemTypeAndChildren(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $formMock  = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');
        $formMock->method('getData')->willReturn(new MenuItem());
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        // itemType != link => no link fields
        $component->itemHasChildren = false;
        $component->formValues      = [
            'live_name' => [
                'basic' => ['itemType' => MenuItem::ITEM_TYPE_SECTION],
            ],
        ];
        self::assertFalse($component->showLinkFields());
        self::assertFalse($component->showParentField());

        // itemType == link and has children => parent visible, link fields hidden
        $component->itemHasChildren = true;
        $component->formValues      = [
            'live_name' => [
                'basic'  => ['itemType' => MenuItem::ITEM_TYPE_LINK],
                'config' => [],
            ],
        ];
        self::assertFalse($component->showLinkFields());
        self::assertTrue($component->showParentField());

        // itemType == link and no children => link fields visible
        $component->itemHasChildren = false;
        self::assertTrue($component->showLinkFields());
    }

    public function testRouteAndExternalVisibilityDependsOnLinkType(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $formMock  = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');
        $formMock->method('getData')->willReturn(new MenuItem());
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        $component->itemHasChildren = false;

        $component->formValues = [
            'live_name' => [
                'basic'  => ['itemType' => MenuItem::ITEM_TYPE_LINK],
                'config' => ['linkType' => MenuItem::LINK_TYPE_ROUTE],
            ],
        ];

        self::assertTrue($component->showRouteFields());
        self::assertFalse($component->showExternalUrlField());

        $component->formValues['live_name']['config']['linkType'] = MenuItem::LINK_TYPE_EXTERNAL;
        self::assertFalse($component->showRouteFields());
        self::assertTrue($component->showExternalUrlField());
    }

    public function testGetLinkTypeFallsBackToFormDataWhenUnsetAndLinkTypeIsNotNull(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $formMock  = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');

        $data = new MenuItem();
        $data->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);

        $formMock->method('getData')->willReturn($data);
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        $component->formValues = [];

        self::assertSame(MenuItem::LINK_TYPE_EXTERNAL, $component->getLinkType());
    }

    public function testGetLinkTypeDefaultsToRouteWhenUnsetAndLinkTypeIsNull(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $formMock  = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');

        $data = new MenuItem();
        $data->setLinkType(null);

        $formMock->method('getData')->willReturn($data);
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        $component->formValues = [];

        self::assertSame(MenuItem::LINK_TYPE_ROUTE, $component->getLinkType());
    }

    public function testGetSuggestedRouteParamsReturnsKeysFromAppRoutes(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $formMock  = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');
        $formMock->method('getData')->willReturn(new MenuItem());
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        $component->appRoutes = [
            'app_home' => ['label' => 'Home', 'params' => ['tab', 'section']],
        ];

        $component->formValues = [
            'live_name' => [
                'basic' => ['routeName' => 'app_home'],
            ],
        ];

        self::assertSame(['tab' => '', 'section' => ''], $component->getSuggestedRouteParams());

        $component->formValues['live_name']['basic']['routeName'] = 'unknown';
        self::assertSame([], $component->getSuggestedRouteParams());
    }

    public function testInstantiateFormUsesFormFactoryWithSectionFocus(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $menu      = new Menu();
        $menuItem  = new MenuItem();

        $component->menu            = $menu;
        $component->appRoutes       = [];
        $component->excludeIds      = [1, 2];
        $component->locale          = 'en';
        $component->locales         = ['en', 'es'];
        $component->initialFormData = $menuItem;
        $component->sectionFocus    = 'basic';

        $formMock = $this->createMock(FormInterface::class);

        $formFactory->expects(self::once())
            ->method('create')
            ->with(
                MenuItemType::class,
                $menuItem,
                self::callback(static fn (array $options): bool => $options['menu'] instanceof Menu
                    && $options['exclude_ids'] === [1, 2]
                    && $options['locale'] === 'en'
                    && $options['available_locales'] === ['en', 'es']
                    && $options['csrf_token_id'] === 'submit'
                    && $options['section'] === 'basic'),
            )
            ->willReturn($formMock);

        $ref = new ReflectionMethod($component, 'instantiateForm');

        $result = $ref->invoke($component);
        self::assertSame($formMock, $result);
    }

    public function testSaveResetsDividerFieldsAndPersists(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag->expects(self::once())
            ->method('add')
            ->with('success', 'Item created.');

        $session = $this->createMock(Session::class);
        $session->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBag);

        $requestStack->expects(self::once())
            ->method('getSession')
            ->willReturn($session);

        $menuItem = new MenuItem();
        $menuItem->setItemType(MenuItem::ITEM_TYPE_DIVIDER);
        $menuItem->setLabel('X');
        $menuItem->setIcon('icon');
        $menuItem->setTranslations(['en' => 'Y']);

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');
        $formMock->method('getData')->willReturn($menuItem);
        $formMock->method('isValid')->willReturn(true);
        $formMock->method('createView')->willReturn($this->createEmptyFormView());

        $formMock->expects(self::once())
            ->method('submit');

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        $em->expects(self::once())->method('persist')->with($menuItem);
        $em->expects(self::once())->method('flush');

        $component->redirectToUrl = '/redirect';
        $component->menu          = new Menu();
        $component->isEdit        = false;
        $component->formValues    = ['live_name' => ['basic' => [], 'config' => []]];

        $response = $component->save();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/redirect', $response->getTargetUrl());

        self::assertSame('', $menuItem->getLabel());
        self::assertNull($menuItem->getIcon());
        self::assertNull($menuItem->getTranslations());
        self::assertNull($menuItem->getParent());
    }

    public function testSaveResetsLinkFieldsWhenChildrenExistAndSetsMenuWhenMissing(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $flashBag = $this->createMock(FlashBagInterface::class);
        $flashBag->expects(self::once())
            ->method('add')
            ->with('success', 'Item updated.');

        $session = $this->createMock(Session::class);
        $session->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBag);

        $requestStack->expects(self::once())
            ->method('getSession')
            ->willReturn($session);

        $parent = new MenuItem();
        $item   = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('app_home');
        $item->setRouteParams(['tab' => '1']);
        $item->setExternalUrl('https://example.com');
        $item->setParent($parent);

        $item->getChildren()->add(new MenuItem());

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');
        $formMock->method('getData')->willReturn($item);
        $formMock->method('isValid')->willReturn(true);
        $formMock->method('createView')->willReturn($this->createEmptyFormView());
        $formMock->expects(self::once())->method('submit');

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        $em->expects(self::once())->method('persist')->with($item);
        $em->expects(self::once())->method('flush');

        $component->redirectToUrl = '/redirect';
        $component->menu          = new Menu();
        $component->isEdit        = true;
        $component->formValues    = ['live_name' => ['basic' => [], 'config' => []]];

        $component->save();

        self::assertNull($item->getLinkType());
        self::assertNull($item->getRouteName());
        self::assertNull($item->getRouteParams());
        self::assertNull($item->getExternalUrl());
        self::assertSame($component->menu, $item->getMenu());
    }
}
