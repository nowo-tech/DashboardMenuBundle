<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\LiveComponent;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Nowo\DashboardMenuBundle\Concurrency\DashboardMenuConflictAnswer;
use Nowo\DashboardMenuBundle\Concurrency\DashboardMenuTransaction;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\MenuItemType;
use Nowo\DashboardMenuBundle\LiveComponent\ItemFormLiveComponent;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Tests\Unit\Concurrency\StorageErrors;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function strlen;

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

    /** Stubs root form + icon subform so {@see ItemFormLiveComponent::getItemType()} reads `icon.itemType` from Live values. */
    /**
     * @param FormInterface<mixed>&MockObject $formMock
     */
    private function stubFormTreeWithIconItemTypeField(FormInterface&MockObject $formMock): void
    {
        $iconForm = $this->createMock(FormInterface::class);
        $iconForm->method('has')->willReturnCallback(static fn (string $name): bool => $name === 'itemType');
        $formMock->method('has')->willReturnCallback(static fn (string $name): bool => $name === 'icon');
        $formMock->method('get')->with('icon')->willReturn($iconForm);
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
        $this->stubFormTreeWithIconItemTypeField($formMock);

        $this->setPrivateTraitProperty($component, 'form', $formMock);

        $component->formValues = [
            'live_name' => [
                'basic'  => [],
                'icon'   => ['itemType' => MenuItem::ITEM_TYPE_DIVIDER],
                'config' => [],
            ],
        ];

        self::assertSame(MenuItem::ITEM_TYPE_DIVIDER, $component->getItemType());
    }

    public function testGetItemTypeIgnoresStaleValuesWhenConfigPartialHasNoIconField(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $formMock  = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');
        $formMock->method('has')->with('icon')->willReturn(false);

        $data = new MenuItem();
        $data->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $formMock->method('getData')->willReturn($data);

        $this->setPrivateTraitProperty($component, 'form', $formMock);

        $component->formValues = [
            'live_name' => [
                'basic'  => ['itemType' => MenuItem::ITEM_TYPE_LINK],
                'config' => ['linkType' => MenuItem::LINK_TYPE_ROUTE],
            ],
        ];

        self::assertSame(MenuItem::ITEM_TYPE_SERVICE, $component->getItemType());
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
        $this->stubFormTreeWithIconItemTypeField($formMock);
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        // itemType != link => no link fields
        $component->itemHasChildren = false;
        $component->formValues      = [
            'live_name' => [
                'basic' => [],
                'icon'  => ['itemType' => MenuItem::ITEM_TYPE_SECTION],
            ],
        ];
        self::assertFalse($component->showLinkFields());
        self::assertTrue($component->showParentField());

        // itemType == link and has children => parent visible, link fields hidden
        $component->itemHasChildren = true;
        $component->formValues      = [
            'live_name' => [
                'basic'  => [],
                'icon'   => ['itemType' => MenuItem::ITEM_TYPE_LINK],
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
        $this->stubFormTreeWithIconItemTypeField($formMock);
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        $component->itemHasChildren = false;

        $component->formValues = [
            'live_name' => [
                'basic'  => [],
                'icon'   => ['itemType' => MenuItem::ITEM_TYPE_LINK],
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

    public function testInstantiateFormHydratesItemIdFromActionUrlWhenItemIdMissing(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component                  = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $component->menu            = new Menu();
        $component->appRoutes       = [];
        $component->excludeIds      = [1, 2];
        $component->locale          = 'en';
        $component->locales         = ['en', 'es'];
        $component->sectionFocus    = 'basic';
        $component->initialFormData = new MenuItem(); // id is expected to be null
        $component->actionUrl       = '/dashboard/menu/1/item/55/edit';

        $fetchedFromDb = new MenuItem();
        $em->expects(self::once())->method('detach')->with(self::isInstanceOf(MenuItem::class));
        $em->expects(self::once())
            ->method('find')
            ->with(MenuItem::class, 55)
            ->willReturn($fetchedFromDb);

        $formMock = $this->createMock(FormInterface::class);

        $formFactory->expects(self::once())
            ->method('create')
            ->with(
                MenuItemType::class,
                $fetchedFromDb,
                self::callback(static fn (array $options): bool => $options['menu'] instanceof Menu
                    && $options['exclude_ids'] === [1, 2]
                    && $options['locale'] === 'en'
                    && $options['available_locales'] === ['en', 'es']
                    && $options['csrf_token_id'] === 'submit'
                    && $options['section'] === 'basic'),
            )
            ->willReturn($formMock);

        $ref    = new ReflectionMethod($component, 'instantiateForm');
        $result = $ref->invoke($component);
        self::assertSame($formMock, $result);
    }

    public function testInstantiateFormRefetchesFromDbWhenSectionConfigEvenIfHydrated(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component                 = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $component->menu           = new Menu();
        $component->appRoutes      = [];
        $component->excludeIds     = [];
        $component->locale         = 'en';
        $component->locales        = ['en'];
        $component->sectionFocus   = 'config';
        $component->itemId         = 10;
        $component->hydratedItemId = 10;

        $fetched = new MenuItem();
        $fetched->setItemType(MenuItem::ITEM_TYPE_SERVICE);

        $em->expects(self::once())->method('detach')->with(self::isInstanceOf(MenuItem::class));
        $em->expects(self::once())
            ->method('find')
            ->with(MenuItem::class, 10)
            ->willReturn($fetched);

        $formMock = $this->createMock(FormInterface::class);
        $formFactory->expects(self::once())
            ->method('create')
            ->with(
                MenuItemType::class,
                $fetched,
                self::callback(static fn (array $options): bool => $options['section'] === 'config'),
            )
            ->willReturn($formMock);

        $ref = new ReflectionMethod($component, 'instantiateForm');
        $ref->invoke($component);
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
        self::assertSame('/redirect', $response->getTargetUrl());

        self::assertSame('', $menuItem->getLabel());
        self::assertNull($menuItem->getIcon());
        self::assertNull($menuItem->getTranslations());
        self::assertNull($menuItem->getParent());
    }

    public function testSaveLockConflictAnswers409WithConflict001(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(StorageErrors::deadlock());
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(new Request());
        $requestStack->expects(self::never())->method('getSession');

        $menuItem = new MenuItem();
        $menuItem->setMenu(new Menu());
        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');
        $formMock->method('getData')->willReturn($menuItem);
        $formMock->method('isValid')->willReturn(true);
        $formMock->method('createView')->willReturn($this->createEmptyFormView());

        $component = new ItemFormLiveComponent($this->createMock(FormFactoryInterface::class), $em, $requestStack, new DashboardMenuTransaction($em), new DashboardMenuConflictAnswer());
        $this->setPrivateTraitProperty($component, 'form', $formMock);
        $component->menu       = new Menu();
        $component->formValues = ['live_name' => ['basic' => [], 'config' => []]];

        try {
            $component->save();
            self::fail('A deadlock must answer the conflict.');
        } catch (HttpException $answer) {
            self::assertSame(409, $answer->getStatusCode());
            self::assertSame('CONFLICT_001', $answer->getHeaders()['X-Conflict-Code']);
        }
    }

    private const TOKEN = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    /**
     * Saves $item through a component whose form returns it, and reports the flash it added.
     *
     * @param EntityManagerInterface&MockObject $em
     *
     * @return array{0: string, 1: string} the flash type and message
     */
    private function saveWithFlash(MenuItem $item, MockObject $em, ?int $baseVersion, ?string $token, bool $isEdit): array
    {
        $flash = ['', ''];
        $bag   = $this->createMock(FlashBagInterface::class);
        $bag->method('add')->willReturnCallback(static function (string $type, mixed $message) use (&$flash): void {
            $flash = [$type, (string) $message];
        });
        $session = $this->createMock(Session::class);
        $session->method('getFlashBag')->willReturn($bag);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('getName')->willReturn('live_name');
        $formMock->method('getData')->willReturn($item);
        $formMock->method('isValid')->willReturn(true);
        $formMock->method('createView')->willReturn($this->createEmptyFormView());

        $component = new ItemFormLiveComponent($this->createMock(FormFactoryInterface::class), $em, $requestStack, new DashboardMenuTransaction($em));
        $this->setPrivateTraitProperty($component, 'form', $formMock);
        $component->redirectToUrl = '/redirect';
        $component->menu          = new Menu();
        $component->isEdit        = $isEdit;
        $component->baseVersion   = $baseVersion;
        $component->submitToken   = $token;
        $component->formValues    = ['live_name' => ['basic' => [], 'config' => []]];

        self::assertSame('/redirect', $component->save()->getTargetUrl());

        return $flash;
    }

    private function savedItem(int $id): MenuItem
    {
        $item = new MenuItem();
        $item->setMenu(new Menu());
        new ReflectionProperty(MenuItem::class, 'id')->setValue($item, $id);

        return $item;
    }

    public function testSaveOfAnExistingItemChecksTheVersionTheAdminSawAndBumpsIt(): void
    {
        $repository = $this->createMock(MenuItemRepository::class);
        $repository->expects(self::once())->method('storedEditVersion')->with(7)->willReturn(3);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects(self::once())->method('persist');
        $item = $this->savedItem(7);

        $flash = $this->saveWithFlash($item, $em, 3, null, true);

        self::assertSame(['success', 'Item updated.'], $flash);
        self::assertSame(2, $item->getEditVersion());
    }

    public function testSaveFromAStaleFormStoresNothingAndFlashesAnError(): void
    {
        $repository = $this->createMock(MenuItemRepository::class);
        $repository->method('storedEditVersion')->willReturn(4);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');
        $item = $this->savedItem(7);

        $flash = $this->saveWithFlash($item, $em, 3, null, true);

        self::assertSame('error', $flash[0]);
        self::assertStringContainsString('changed by someone else', $flash[1]);
    }

    public function testSaveWithoutTheVersionTheFormWasBuiltFromIsStale(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));
        $em->expects(self::never())->method('persist');

        self::assertSame('error', $this->saveWithFlash($this->savedItem(7), $em, null, null, true)[0]);
    }

    public function testSaveOfANewItemStoresItsSubmitToken(): void
    {
        $repository = $this->createMock(MenuItemRepository::class);
        $repository->method('submitTokenExists')->with(self::TOKEN)->willReturn(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects(self::once())->method('persist');
        $item = new MenuItem();

        $flash = $this->saveWithFlash($item, $em, null, self::TOKEN, false);

        self::assertSame(['success', 'Item created.'], $flash);
        self::assertSame(self::TOKEN, $item->getSubmitToken());
    }

    public function testSaveOfAFormPostedTwiceCreatesNothingTheSecondTime(): void
    {
        $repository = $this->createMock(MenuItemRepository::class);
        $repository->method('submitTokenExists')->willReturn(true);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->expects(self::never())->method('persist');

        $flash = $this->saveWithFlash(new MenuItem(), $em, null, self::TOKEN, false);

        self::assertSame('success', $flash[0]);
        self::assertStringContainsString('already created', $flash[1]);
    }

    public function testSaveLosingTheTokensUniqueKeyAnswersAsAlreadyCreated(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->createMock(EntityRepository::class));
        $em->method('flush')->willThrowException(StorageErrors::uniqueViolation());

        $flash = $this->saveWithFlash(new MenuItem(), $em, null, self::TOKEN, false);

        self::assertStringContainsString('already created', $flash[1]);
    }

    public function testInstantiateFormKeepsTheVersionOfTheFirstRender(): void
    {
        $fetched = new MenuItem();
        new ReflectionProperty(MenuItem::class, 'editVersion')->setValue($fetched, 5);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($fetched);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($this->createMock(FormInterface::class));
        $component                  = new ItemFormLiveComponent($formFactory, $em, $this->createMock(RequestStack::class));
        $component->menu            = new Menu();
        $component->itemId          = 9;
        $component->initialFormData = new MenuItem();

        new ReflectionMethod($component, 'instantiateForm')->invoke($component);
        self::assertSame(5, $component->baseVersion);

        // A later re-render reads a newer row: the version the admin saw stays.
        new ReflectionProperty(MenuItem::class, 'editVersion')->setValue($fetched, 6);
        $component->hydratedItemId = null;
        new ReflectionMethod($component, 'instantiateForm')->invoke($component);
        self::assertSame(5, $component->baseVersion);
    }

    public function testInstantiateFormOfANewItemGetsOneSubmitToken(): void
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($this->createMock(FormInterface::class));
        $component       = new ItemFormLiveComponent($formFactory, $this->createMock(EntityManagerInterface::class), $this->createMock(RequestStack::class));
        $component->menu = new Menu();

        new ReflectionMethod($component, 'instantiateForm')->invoke($component);
        $first = $component->submitToken;
        new ReflectionMethod($component, 'instantiateForm')->invoke($component);

        self::assertSame(32, strlen((string) $first));
        self::assertSame($first, $component->submitToken, 'one token per form life');
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

    public function testGetTranslationsDebugReturnsTranslationsWhenFormDataIsMenuItem(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);

        $formMock = $this->createMock(FormInterface::class);
        $menuItem = new MenuItem();
        $menuItem->setTranslations(['en' => 'Hello', 'es' => 'Hola']);

        $formMock->method('getData')->willReturn($menuItem);
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        self::assertSame(['en' => 'Hello', 'es' => 'Hola'], $component->getTranslationsDebug());
    }

    public function testGetTranslationsDebugReturnsEmptyArrayWhenFormDataIsNotMenuItem(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('getData')->willReturn(new stdClass());
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        self::assertSame([], $component->getTranslationsDebug());
    }

    public function testGetTranslationsKeysDebugReturnsCommaJoinedKeys(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component = new ItemFormLiveComponent($formFactory, $em, $requestStack);

        $menuItem = new MenuItem();
        $menuItem->setTranslations(['en' => 'Hello', 'es' => 'Hola']);

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('getData')->willReturn($menuItem);
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        self::assertSame('en,es', $component->getTranslationsKeysDebug());
    }

    public function testGetLocaleFieldValuesDebugReturnsEmptyArrayWhenBasicNotPresent(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component          = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $component->locales = ['en', 'es'];

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('has')->willReturn(false);
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        self::assertSame([], $component->getLocaleFieldValuesDebug());
    }

    public function testGetLocaleFieldValuesDebugReturnsValuesForLocaleFields(): void
    {
        $formFactory  = $this->createMock(FormFactoryInterface::class);
        $em           = $this->createMock(EntityManagerInterface::class);
        $requestStack = $this->createMock(RequestStack::class);

        $component          = new ItemFormLiveComponent($formFactory, $em, $requestStack);
        $component->locales = ['en', 'es'];

        $fieldEn = $this->createMock(FormInterface::class);
        $fieldEn->method('getData')->willReturn('ValueEn');

        $basicForm = $this->createMock(FormInterface::class);
        $basicForm->method('has')->willReturnCallback(static fn (string $name): bool => $name === 'label_en');
        $basicForm->method('get')->willReturnCallback(static fn (string $name): FormInterface => match ($name) {
            'label_en' => $fieldEn,
            default    => $fieldEn,
        });

        $formMock = $this->createMock(FormInterface::class);
        $formMock->method('has')->willReturnCallback(static fn (string $name): bool => $name === 'basic');
        $formMock->method('get')->willReturn($basicForm);
        $this->setPrivateTraitProperty($component, 'form', $formMock);

        self::assertSame(['en' => 'ValueEn'], $component->getLocaleFieldValuesDebug());
    }
}
