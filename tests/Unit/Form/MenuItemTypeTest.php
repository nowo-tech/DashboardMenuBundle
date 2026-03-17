<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
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
        $repo     = $this->createStub(MenuItemRepository::class);
        $type     = new MenuItemType($repo, 'en', []);
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
        $repo     = $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, null);
        $type     = new MenuItemType($repo, 'en', []);

        $type->buildForm($builder, [
            'app_routes'        => ['app_home' => ['label' => 'Home', 'params' => []]],
            'available_locales' => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
        ]);

        self::assertGreaterThanOrEqual(10, count($addCalls));
        self::assertNotNull($this->findAddCall($addCalls, 'label'));
        self::assertNotNull($this->findAddCall($addCalls, 'itemType'));
        self::assertNotNull($this->findAddCall($addCalls, 'routeName'));
        self::assertNotNull($this->findAddCall($addCalls, 'icon'));
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
        $type     = new MenuItemType($repo, 'en', []);

        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => [],
            'menu'              => $menu,
            'exclude_ids'       => [1, 2],
            'locale'            => 'en',
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
        $type     = new MenuItemType($repo, 'en', []);

        $type->buildForm($builder, [
            'app_routes' => [
                'app_home' => ['label' => 'Home', 'params' => []],
                'app_page' => ['label' => 'Page', 'params' => ['page']],
            ],
            'available_locales' => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
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
        $type     = new MenuItemType($repo, 'en', []);

        $type->buildForm($builder, [
            'app_routes' => [
                'app_page' => ['label' => 'Page', 'params' => ['section', 'tab']],
            ],
            'available_locales' => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
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
        $repo      = $this->createStub(MenuItemRepository::class);
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

        $type = new MenuItemType($repo, 'en', ['en', 'es']);
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => ['en', 'es'],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
        ]);

        self::assertArrayHasKey(FormEvents::PRE_SET_DATA, $listeners);
        self::assertArrayHasKey(FormEvents::SUBMIT, $listeners);
    }

    public function testPreSetDataListenerAddsLocaleFieldsWhenDataIsMenuItem(): void
    {
        $repo      = $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemType($repo, 'en', ['en', 'es']);
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => ['en', 'es'],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
        ]);

        $item = new MenuItem();
        $item->setTranslations(['en' => 'Home', 'es' => 'Inicio']);
        $formAdds = [];
        $form     = $this->createMock(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, $type, array $options = []) use (&$formAdds): \PHPUnit\Framework\MockObject\MockObject {
            $formAdds[] = ['name' => $name, 'data' => $options['data'] ?? null];

            return $this->createMock(FormInterface::class);
        });
        $event = new FormEvent($form, $item);
        $listeners[FormEvents::PRE_SET_DATA]($event);

        self::assertCount(2, $formAdds);
        self::assertSame('label_en', $formAdds[0]['name']);
        self::assertSame('Home', $formAdds[0]['data']);
        self::assertSame('label_es', $formAdds[1]['name']);
        self::assertSame('Inicio', $formAdds[1]['data']);
    }

    public function testPreSetDataListenerDoesNothingWhenDataIsNotMenuItem(): void
    {
        $repo      = $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemType($repo, 'en', ['en', 'es']);
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => ['en', 'es'],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
        ]);

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::never())->method('add');
        $event = new FormEvent($form, null);
        $listeners[FormEvents::PRE_SET_DATA]($event);
    }

    public function testSubmitListenerMergesLocaleFieldsIntoTranslations(): void
    {
        $repo      = $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemType($repo, 'en', ['en', 'es']);
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
        $repo      = $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemType($repo, 'en', ['en', 'es']);
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
        $repo      = $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemType($repo, 'en', ['en', 'es']);
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
        $repo      = $this->createStub(MenuItemRepository::class);
        $listeners = [];
        $builder   = $this->createFormBuilderWithListeners($listeners);
        $type      = new MenuItemType($repo, 'en', ['en', 'es']);
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
        $type     = new MenuItemType($repo, 'en', [], $translator);

        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
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
