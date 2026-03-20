<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\MenuItemBasicType;
use Nowo\DashboardMenuBundle\Form\MenuItemConfigType;
use Nowo\DashboardMenuBundle\Form\MenuItemType;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

use function count;

final class MenuItemTypeTest extends TestCase
{
    public function testConfigureOptions(): void
    {
        $type     = new MenuItemType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([]);
        self::assertSame(MenuItem::class, $options['data_class']);
        self::assertSame([], $options['app_routes']);
        self::assertNull($options['menu']);
        self::assertSame([], $options['exclude_ids']);
        self::assertSame('en', $options['locale']);
        self::assertSame([], $options['available_locales']);
        self::assertSame('POST', $options['method']);

        $withOptions = $resolver->resolve([
            'app_routes'        => ['home' => ['label' => 'Home', 'params' => []]],
            'menu'              => new Menu(),
            'exclude_ids'       => [1],
            'locale'            => 'es',
            'available_locales' => ['en', 'es'],
            'action'            => '/item/save',
        ]);
        self::assertSame('es', $withOptions['locale']);
        self::assertSame(['en', 'es'], $withOptions['available_locales']);
        self::assertSame('/item/save', $withOptions['action']);
    }

    public function testBuildFormWithEmptyAvailableLocalesAddsCoreFields(): void
    {
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, null);
        $type     = new MenuItemBasicType([]);

        $type->buildForm($builder, [
            'available_locales' => [],
        ]);

        self::assertGreaterThanOrEqual(3, count($addCalls));
        self::assertNotNull($this->findAddCall($addCalls, 'label'));
        self::assertNotNull($this->findAddCall($addCalls, 'itemType'));
        $iconCall = $this->findAddCall($addCalls, 'icon');
        self::assertNotNull($iconCall);
        // When nowo-tech/icon-selector-bundle is not installed, icon field is TextType.
        // Otherwise it uses IconSelectorType::MODE_TOM_SELECT.
        $expectedIconType = class_exists('Nowo\\IconSelectorBundle\\Form\\IconSelectorType')
            ? 'Nowo\\IconSelectorBundle\\Form\\IconSelectorType'
            : \Symfony\Component\Form\Extension\Core\Type\TextType::class;
        self::assertSame($expectedIconType, $iconCall['type']);
    }

    public function testBuildFormUsesIconSelectorTypeWhenClassExists(): void
    {
        if (!class_exists('Nowo\\IconSelectorBundle\\Form\\IconSelectorType')) {
            eval(<<<'PHP'
namespace Nowo\IconSelectorBundle\Form;
final class IconSelectorType
{
    public const MODE_TOM_SELECT = 'tom_select';
}
PHP);
        }

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, null);
        $type     = new MenuItemBasicType([]);

        $type->buildForm($builder, [
            'available_locales' => [],
        ]);

        $iconCall = $this->findAddCall($addCalls, 'icon');
        self::assertNotNull($iconCall);
        self::assertSame('Nowo\\IconSelectorBundle\\Form\\IconSelectorType', $iconCall['type']);
    }

    public function testBuildFormUsesTranslatorForPlaceholdersWhenProvided(): void
    {
        $repo = $this->createStub(MenuItemRepository::class);
        $t    = $this->createStub(TranslatorInterface::class);
        $t->method('trans')->willReturnCallback(static fn (string $id): string => 't:' . $id);

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, null);
        $type     = new MenuItemConfigType($repo, [], 'en', $t);

        $type->buildForm($builder, [
            'app_routes'  => [],
            'menu'        => null,
            'exclude_ids' => [],
            'locale'      => 'en',
        ]);

        $routeNameRaw = $this->findAddCallRaw($addCalls, 'routeName');
        $placeholder  = $routeNameRaw['options']['placeholder'] ?? null;
        self::assertSame('t:form.menu_item_type.route_name.placeholder', $placeholder);
    }

    public function testBuildFormWithMenuAddsParentField(): void
    {
        $menu = new Menu();
        $qb   = $this->createStub(\Doctrine\ORM\QueryBuilder::class);
        $repo = $this->createMock(MenuItemRepository::class);
        $repo->expects(self::once())
            ->method('getPossibleParentsQueryBuilder')
            ->with($menu, [1, 2])
            ->willReturn($qb);

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, null);
        $type     = new MenuItemConfigType($repo, [], 'en');

        $type->buildForm($builder, [
            'app_routes'  => [],
            'menu'        => $menu,
            'exclude_ids' => [1, 2],
            'locale'      => 'en',
        ]);

        $parentCall = $this->findAddCall($addCalls, 'parent');
        self::assertNotNull($parentCall);
        self::assertSame(\Symfony\Bridge\Doctrine\Form\Type\EntityType::class, $parentCall['type']);
        self::assertSame($qb, $parentCall['query_builder']);

        $parentRaw   = $this->findAddCallRaw($addCalls, 'parent');
        $choiceLabel = $parentRaw['options']['choice_label'] ?? null;
        self::assertIsCallable($choiceLabel);
        $child = new MenuItem();
        $child->setLabel('Child');
        $parent = new MenuItem();
        $parent->setLabel('Parent');
        $child->setParent($parent);
        self::assertSame('Parent > Child', $choiceLabel($child));
    }

    public function testBuildFormBuildsRouteChoicesFromAppRoutes(): void
    {
        $repo     = $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, null);
        $type     = new MenuItemConfigType($repo, [], 'en');

        $type->buildForm($builder, [
            'app_routes' => [
                'app_home' => ['label' => 'Home', 'params' => []],
                'app_page' => ['label' => 'Page', 'params' => ['page']],
            ],
            'menu'        => null,
            'exclude_ids' => [],
            'locale'      => 'en',
        ]);

        $routeNameCall = $this->findAddCall($addCalls, 'routeName');
        self::assertNotNull($routeNameCall);
        self::assertArrayHasKey('Home', $routeNameCall['choices']);
        self::assertSame('app_home', $routeNameCall['choices']['Home']);
        self::assertArrayHasKey('Page', $routeNameCall['choices']);
        self::assertSame('app_page', $routeNameCall['choices']['Page']);
    }

    public function testRouteNameChoiceAttrClosureReturnsDataParams(): void
    {
        $repo     = $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, null);
        $type     = new MenuItemConfigType($repo, [], 'en');

        $type->buildForm($builder, [
            'app_routes' => [
                'app_page' => ['label' => 'Page', 'params' => ['section', 'tab']],
            ],
            'menu'        => null,
            'exclude_ids' => [],
            'locale'      => 'en',
        ]);

        $routeNameCall = $this->findAddCallRaw($addCalls, 'routeName');
        self::assertNotNull($routeNameCall);
        $choiceAttr = $routeNameCall['options']['choice_attr'] ?? null;
        self::assertIsCallable($choiceAttr);
        $attr = $choiceAttr('app_page', 'Page', 'app_page');
        self::assertArrayHasKey('data-params', $attr);
        self::assertSame('["section","tab"]', $attr['data-params']);

        $attrUnknown = $choiceAttr('unknown_route', 'Unknown', 'unknown_route');
        self::assertSame('[]', $attrUnknown['data-params']);
    }

    public function testBuildFormWithAvailableLocalesAddsEventListener(): void
    {
        $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->method('get')->with('routeParams')->willReturn(
            $this->createMock(FormBuilderInterface::class),
        );
        $builder->method('addEventListener')->willReturnCallback(function (string $event, callable $listener) use (&$listeners): \PHPUnit\Framework\MockObject\MockObject {
            $listeners[$event] = $listener;

            return $this->createMock(FormBuilderInterface::class);
        });

        $type = new MenuItemBasicType(['en', 'es']);
        $type->buildForm($builder, [
            'available_locales' => ['en', 'es'],
        ]);

        self::assertArrayHasKey(FormEvents::SUBMIT, $listeners);
        self::assertArrayHasKey(FormEvents::PRE_SUBMIT, $listeners);
    }

    public function testPreSetDataListenerAddsLocaleFieldsWhenDataIsMenuItem(): void
    {
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemBasicType(['en', 'es']);
        $type->buildForm($builder, [
            'available_locales' => ['en', 'es'],
        ]);

        $form = $this->createMock(FormInterface::class);
        $data = [
            'itemType' => MenuItem::ITEM_TYPE_DIVIDER,
            'label'    => 'SHOULD_BE_CLEARED',
            'icon'     => 'some-icon',
            'label_en' => 'Home',
            'label_es' => 'Inicio',
        ];

        $event = new FormEvent($form, $data);
        $listeners[FormEvents::PRE_SUBMIT]($event);

        $out = $event->getData();
        self::assertSame('', $out['label']);
        self::assertNull($out['icon']);
        self::assertNull($out['label_en']);
        self::assertNull($out['label_es']);
    }

    public function testPreSetDataListenerDoesNothingWhenDataIsNotMenuItem(): void
    {
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemBasicType(['en', 'es']);
        $type->buildForm($builder, [
            'available_locales' => ['en', 'es'],
        ]);

        $form = $this->createMock(FormInterface::class);
        $data = [
            'itemType' => MenuItem::ITEM_TYPE_LINK,
            'label'    => 'KEEP',
            'icon'     => 'some-icon',
            'label_en' => 'Home',
            'label_es' => 'Inicio',
        ];

        $event = new FormEvent($form, $data);
        $listeners[FormEvents::PRE_SUBMIT]($event);

        $out = $event->getData();
        self::assertSame('KEEP', $out['label']);
        self::assertSame('some-icon', $out['icon']);
    }

    public function testSubmitListenerMergesLocaleFieldsIntoTranslations(): void
    {
        $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemBasicType(['en', 'es']);
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => ['en', 'es'],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
        ]);

        $item = new MenuItem();
        $form = $this->createMock(FormInterface::class);
        $form->method('has')->willReturnMap([['label_en', true], ['label_es', true]]);
        $labelEn = $this->createMock(FormInterface::class);
        $labelEn->method('getData')->willReturn('Home');
        $labelEs = $this->createMock(FormInterface::class);
        $labelEs->method('getData')->willReturn('Casa');
        $form->method('get')->willReturnMap([['label_en', $labelEn], ['label_es', $labelEs]]);
        $event = new FormEvent($form, $item);
        $listeners[FormEvents::SUBMIT]($event);

        self::assertSame($item, $event->getData());
        self::assertSame(['en' => 'Home', 'es' => 'Casa'], $item->getTranslations());
    }

    public function testSubmitListenerUnsetsEmptyLocaleValues(): void
    {
        $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemBasicType(['en', 'es']);
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => ['en', 'es'],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
        ]);

        $item = new MenuItem();
        $item->setTranslations(['en' => 'Home', 'es' => 'Inicio']);
        $form = $this->createMock(FormInterface::class);
        $form->method('has')->willReturnMap([['label_en', true], ['label_es', true]]);
        $labelEn = $this->createMock(FormInterface::class);
        $labelEn->method('getData')->willReturn('');
        $labelEs = $this->createMock(FormInterface::class);
        $labelEs->method('getData')->willReturn(null);
        $form->method('get')->willReturnMap([['label_en', $labelEn], ['label_es', $labelEs]]);
        $event = new FormEvent($form, $item);
        $listeners[FormEvents::SUBMIT]($event);

        self::assertNull($item->getTranslations());
    }

    public function testSubmitListenerDoesNothingWhenDataIsNotMenuItem(): void
    {
        $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemBasicType(['en', 'es']);
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => ['en', 'es'],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
        ]);

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::never())->method('get');
        $event = new FormEvent($form, null);
        $listeners[FormEvents::SUBMIT]($event);
        self::assertNull($event->getData());
    }

    public function testSubmitListenerSkipsLocaleFieldWhenFormDoesNotHaveIt(): void
    {
        $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemBasicType(['en', 'es']);
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => ['en', 'es'],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
        ]);

        $item = new MenuItem();
        $form = $this->createMock(FormInterface::class);
        $form->method('has')->willReturnMap([['label_en', true], ['label_es', false]]);
        $labelEn = $this->createMock(FormInterface::class);
        $labelEn->method('getData')->willReturn('Home');
        $form->method('get')->with('label_en')->willReturn($labelEn);
        $event = new FormEvent($form, $item);
        $listeners[FormEvents::SUBMIT]($event);

        self::assertSame(['en' => 'Home'], $item->getTranslations());
    }

    public function testBuildFormWithTranslatorUsesTranslatorForPlaceholders(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id . '_translated');
        $repo     = $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, null);
        $type     = new MenuItemConfigType($repo, [], 'en', $translator);

        $type->buildForm($builder, [
            'app_routes'  => [],
            'menu'        => null,
            'exclude_ids' => [],
            'locale'      => 'en',
        ]);

        $routeNameCall = $this->findAddCall($addCalls, 'routeName');
        self::assertNotNull($routeNameCall);
        $placeholder = $routeNameCall['placeholder'] ?? null;
        self::assertIsString($placeholder);
        self::assertStringContainsString('_translated', $placeholder);
    }

    private function createFormBuilderMock(array &$addCalls, ?object $data): FormBuilderInterface
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('getData')->willReturn($data);
        $builder->method('add')->willReturnCallback(static function (string $name, $type, array $options = []) use (&$addCalls, $builder): \PHPUnit\Framework\MockObject\MockObject {
            $addCalls[] = ['name' => $name, 'type' => $type, 'options' => $options];

            return $builder;
        });
        $routeParamsBuilder = $this->createMock(FormBuilderInterface::class);
        $routeParamsBuilder->method('addModelTransformer')->willReturnSelf();
        $builder->method('get')->with('routeParams')->willReturn($routeParamsBuilder);
        $builder->method('addEventListener')->willReturnSelf();

        return $builder;
    }

    private function createFormBuilderWithListeners(array &$listeners): FormBuilderInterface
    {
        $addCalls = [];
        $builder  = $this->createMock(FormBuilderInterface::class);
        $builder->method('getData')->willReturn(null);
        $builder->method('add')->willReturnCallback(static function (string $name, $type, array $options = []) use (&$addCalls, $builder): \PHPUnit\Framework\MockObject\MockObject {
            $addCalls[] = ['name' => $name];

            return $builder;
        });
        $routeParamsBuilder = $this->createMock(FormBuilderInterface::class);
        $routeParamsBuilder->method('addModelTransformer')->willReturnSelf();
        $builder->method('get')->with('routeParams')->willReturn($routeParamsBuilder);
        $builder->method('addEventListener')->willReturnCallback(static function (string $event, callable $listener) use (&$listeners, $builder): \PHPUnit\Framework\MockObject\MockObject {
            $listeners[$event] = $listener;

            return $builder;
        });

        return $builder;
    }

    private function findAddCall(array $addCalls, string $name): ?array
    {
        $raw = $this->findAddCallRaw($addCalls, $name);
        if ($raw === null) {
            return null;
        }

        return array_merge(['type' => $raw['type']], $raw['options']);
    }

    private function findAddCallRaw(array $addCalls, string $name): ?array
    {
        foreach ($addCalls as $call) {
            if ($call['name'] === $name) {
                return $call;
            }
        }

        return null;
    }
}
