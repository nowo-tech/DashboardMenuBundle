<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Doctrine\ORM\QueryBuilder;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\MenuItemBasicType;
use Nowo\DashboardMenuBundle\Form\MenuItemConfigType;
use Nowo\DashboardMenuBundle\Form\MenuItemType;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_array;

final class MenuItemTypeTest extends TestCase
{
    public function testMenuItemTypeConfigureOptions(): void
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
        self::assertNull($options['section'] ?? null);
        self::assertSame('POST', $options['method']);
    }

    public function testMenuItemTypeBuildFormAddsBasicAndConfigByDefault(): void
    {
        $repo     = $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuItemType($repo, 'en', []);
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
        ]);

        $names = array_map(static fn (array $c): string => $c['name'], $addCalls);
        self::assertContains('basic', $names);
        self::assertContains('config', $names);
    }

    public function testMenuItemTypeBuildFormAddsOnlyBasicWhenSectionBasic(): void
    {
        $repo     = $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuItemType($repo, 'en', []);
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
            'section'           => 'basic',
        ]);

        $names = array_map(static fn (array $c): string => $c['name'], $addCalls);
        self::assertContains('basic', $names);
        self::assertNotContains('config', $names);
    }

    public function testMenuItemTypeBuildFormAddsOnlyConfigWhenSectionConfig(): void
    {
        $repo     = $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuItemType($repo, 'en', []);
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
            'section'           => 'config',
        ]);

        $names = array_map(static fn (array $c): string => $c['name'], $addCalls);
        self::assertNotContains('basic', $names);
        self::assertContains('config', $names);
    }

    public function testMenuItemBasicTypeBuildFormAddsCoreFieldsAndIconIsTextWhenIconSelectorMissing(): void
    {
        $availableLocales = [];
        $type             = new MenuItemBasicType(availableLocales: $availableLocales);

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, new MenuItem());

        $type->buildForm($builder, ['available_locales' => $availableLocales]);

        $iconCall = $this->findAddCall($addCalls, 'icon');
        if (class_exists('Nowo\\IconSelectorBundle\\Form\\IconSelectorType')) {
            self::assertNotNull($iconCall);
            self::assertNotSame(TextType::class, $iconCall['type']);
        } else {
            self::assertNotNull($iconCall);
            self::assertSame(TextType::class, $iconCall['type']);
        }
    }

    public function testMenuItemBasicTypeBuildFormUsesIconSelectorWhenAvailable(): void
    {
        if (!class_exists('Nowo\\IconSelectorBundle\\Form\\IconSelectorType')) {
            eval('namespace Nowo\\IconSelectorBundle\\Form; class IconSelectorType { public const MODE_TOM_SELECT = "tom_select"; }');
        }

        $availableLocales = [];
        $type             = new MenuItemBasicType(availableLocales: $availableLocales);

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, new MenuItem());

        $type->buildForm($builder, ['available_locales' => $availableLocales]);

        $iconCall = $this->findAddCall($addCalls, 'icon');
        self::assertNotNull($iconCall);
        self::assertSame(\Nowo\IconSelectorBundle\Form\IconSelectorType::class, $iconCall['type']);
        self::assertFalse($iconCall['required']);
        self::assertSame([], $iconCall['constraints']);
        self::assertSame(\Nowo\IconSelectorBundle\Form\IconSelectorType::MODE_TOM_SELECT, $iconCall['mode']);
        self::assertSame(
            NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
            $iconCall['translation_domain'],
        );
        self::assertArrayHasKey('placeholder', $iconCall['attr']);
    }

    public function testMenuItemBasicTypeAddsLocaleFieldsAndPreSubmitClearsDividerValues(): void
    {
        $availableLocales = ['en', 'es'];

        $menuItem = new MenuItem();
        $menuItem->setTranslations(['en' => 'Home']);

        $type = new MenuItemBasicType(availableLocales: $availableLocales);

        $addCalls       = [];
        $eventListeners = [];
        $builder        = $this->createFormBuilderMock(
            $addCalls,
            $menuItem,
            eventListeners: $eventListeners,
            captureEventListeners: true,
        );

        $type->buildForm($builder, ['available_locales' => $availableLocales]);

        self::assertNotNull($this->findAddCall($addCalls, 'label_en'));
        self::assertNotNull($this->findAddCall($addCalls, 'label_es'));

        self::assertArrayHasKey(FormEvents::PRE_SUBMIT, $eventListeners);
        $preSubmitListener = $eventListeners[FormEvents::PRE_SUBMIT];

        $event = $this->createMock(FormEvent::class);
        $event->method('getData')->willReturn([
            'itemType' => MenuItem::ITEM_TYPE_DIVIDER,
            'label'    => 'X',
            'icon'     => 'some-icon',
            'label_en' => 'should-clear',
            'label_es' => 'should-clear',
        ]);
        $event->expects(self::once())
            ->method('setData')
            ->with(self::callback(static function (mixed $data): bool {
                if (!is_array($data)) {
                    return false;
                }

                return $data['label'] === ''
                    && $data['icon'] === null
                    && $data['label_en'] === null
                    && $data['label_es'] === null;
            }));

        $preSubmitListener($event);
    }

    public function testMenuItemBasicTypeSubmitListenerUpdatesTranslations(): void
    {
        $availableLocales = ['en', 'es', 'fr'];

        $menuItem = new MenuItem();
        $menuItem->setTranslations([
            'en' => 'Home',
            'es' => 'Old ES',
            'fr' => 'Old FR',
        ]);

        $type = new MenuItemBasicType(availableLocales: $availableLocales);

        $addCalls       = [];
        $eventListeners = [];
        $builder        = $this->createFormBuilderMock(
            $addCalls,
            $menuItem,
            eventListeners: $eventListeners,
            captureEventListeners: true,
        );

        $type->buildForm($builder, ['available_locales' => $availableLocales]);

        self::assertArrayHasKey(FormEvents::SUBMIT, $eventListeners);
        $submitListener = $eventListeners[FormEvents::SUBMIT];

        $labelEn = $this->createMock(FormInterface::class);
        $labelEn->method('getData')->willReturn('New Home');
        $labelEn->method('isSubmitted')->willReturn(true);

        $labelEs = $this->createMock(FormInterface::class);
        $labelEs->method('getData')->willReturn('');
        $labelEs->method('isSubmitted')->willReturn(true);

        $form = $this->createMock(FormInterface::class);
        $form->method('has')->willReturnCallback(static fn (string $name): bool => match ($name) {
            'label_en' => true,
            'label_es' => true,
            'label_fr' => false,
            default    => false,
        });
        $form->method('get')->willReturnCallback(static fn (string $name): FormInterface => match ($name) {
            'label_en' => $labelEn,
            'label_es' => $labelEs,
            default    => $labelEn,
        });

        $formParent = $this->createMock(FormInterface::class);
        $formParent->method('getData')->willReturn($menuItem);
        $form->method('getParent')->willReturn($formParent);

        $event = $this->createMock(FormEvent::class);
        $event->method('getData')->willReturn(new stdClass());
        $event->method('getForm')->willReturn($form);

        $event->expects(self::once())->method('setData')->with($menuItem);

        $submitListener($event);

        self::assertSame(['en' => 'New Home', 'fr' => 'Old FR'], $menuItem->getTranslations());
    }

    public function testMenuItemBasicTypeSubmitListenerDoesNotTouchTranslationsWhenLocaleFieldNotSubmitted(): void
    {
        $availableLocales = ['en', 'es'];

        $menuItem = new MenuItem();
        $menuItem->setTranslations([
            'en' => 'Home',
            'es' => 'Old ES',
        ]);

        $type = new MenuItemBasicType(availableLocales: $availableLocales);

        $addCalls       = [];
        $eventListeners = [];
        $builder        = $this->createFormBuilderMock(
            $addCalls,
            $menuItem,
            eventListeners: $eventListeners,
            captureEventListeners: true,
        );

        $type->buildForm($builder, ['available_locales' => $availableLocales]);

        $submitListener = $eventListeners[FormEvents::SUBMIT];

        $labelEn = $this->createMock(FormInterface::class);
        $labelEn->method('getData')->willReturn('New Home');
        $labelEn->method('isSubmitted')->willReturn(true);

        $labelEs = $this->createMock(FormInterface::class);
        $labelEs->method('getData')->willReturn('');
        $labelEs->method('isSubmitted')->willReturn(false);

        $form = $this->createMock(FormInterface::class);
        $form->method('has')->willReturnCallback(static fn (string $name): bool => match ($name) {
            'label_en' => true,
            'label_es' => true,
            default    => false,
        });
        $form->method('get')->willReturnCallback(static fn (string $name): FormInterface => match ($name) {
            'label_en' => $labelEn,
            'label_es' => $labelEs,
            default    => $labelEn,
        });

        $formParent = $this->createMock(FormInterface::class);
        $formParent->method('getData')->willReturn($menuItem);
        $form->method('getParent')->willReturn($formParent);

        $event = $this->createMock(FormEvent::class);
        $event->method('getData')->willReturn(new stdClass());
        $event->method('getForm')->willReturn($form);
        $event->expects(self::once())->method('setData')->with($menuItem);

        $submitListener($event);

        self::assertSame(['en' => 'New Home', 'es' => 'Old ES'], $menuItem->getTranslations());
    }

    public function testMenuItemBasicTypeSubmitListenerReturnsEarlyWhenNotMenuItem(): void
    {
        $availableLocales = ['en', 'es'];
        $type             = new MenuItemBasicType(availableLocales: $availableLocales);

        $addCalls       = [];
        $eventListeners = [];
        $builder        = $this->createFormBuilderMock(
            $addCalls,
            new MenuItem(),
            eventListeners: $eventListeners,
            captureEventListeners: true,
        );

        $type->buildForm($builder, ['available_locales' => $availableLocales]);

        self::assertArrayHasKey(FormEvents::SUBMIT, $eventListeners);
        $submitListener = $eventListeners[FormEvents::SUBMIT];

        $form       = $this->createMock(FormInterface::class);
        $formParent = $this->createMock(FormInterface::class);
        $formParent->method('getData')->willReturn(new stdClass());
        $form->method('getParent')->willReturn($formParent);

        $event = $this->createMock(FormEvent::class);
        $event->method('getData')->willReturn(new stdClass());
        $event->method('getForm')->willReturn($form);
        $event->expects(self::never())->method('setData');

        $submitListener($event);
    }

    public function testMenuItemBasicTypeValidateLabelWhenNotDividerEarlyReturnsForDivider(): void
    {
        $type = new MenuItemBasicType(availableLocales: []);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_DIVIDER);
        $item->setLabel('');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        $type->validateLabelWhenNotDivider($item, $context);
    }

    public function testMenuItemBasicTypePreSubmitListenerReturnsEarlyWhenNotDivider(): void
    {
        $availableLocales = ['en', 'es'];
        $type             = new MenuItemBasicType(availableLocales: $availableLocales);

        $addCalls       = [];
        $eventListeners = [];
        $builder        = $this->createFormBuilderMock(
            $addCalls,
            new MenuItem(),
            eventListeners: $eventListeners,
            captureEventListeners: true,
        );

        $type->buildForm($builder, ['available_locales' => $availableLocales]);

        self::assertArrayHasKey(FormEvents::PRE_SUBMIT, $eventListeners);
        $preSubmitListener = $eventListeners[FormEvents::PRE_SUBMIT];

        $event = $this->createMock(FormEvent::class);
        $event->method('getData')->willReturn([
            'itemType' => MenuItem::ITEM_TYPE_LINK,
            'label'    => 'should-stay',
            'icon'     => 'some-icon',
            'label_en' => 'keep',
            'label_es' => 'keep',
        ]);

        $event->expects(self::never())->method('setData');
        $preSubmitListener($event);
    }

    public function testMenuItemBasicTypeConfigureOptionsSetsDefaultsAndAllowedTypes(): void
    {
        $availableLocales = ['en', 'es'];
        $type             = new MenuItemBasicType(availableLocales: $availableLocales);

        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([]);
        self::assertSame(MenuItem::class, $options['data_class']);
        self::assertSame($availableLocales, $options['available_locales']);
        self::assertSame(NowoDashboardMenuBundle::TRANSLATION_DOMAIN, $options['translation_domain']);

        self::assertArrayHasKey('constraints', $options);
        self::assertCount(1, $options['constraints']);
        self::assertInstanceOf(Callback::class, $options['constraints'][0]);

        $resolvedWithOverride = $resolver->resolve(['available_locales' => ['fr']]);
        self::assertSame(['fr'], $resolvedWithOverride['available_locales']);

        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $resolver->resolve(['available_locales' => 'not-an-array']);
    }

    public function testMenuItemBasicTypeValidateLabelWhenNotDividerAddsViolation(): void
    {
        $type = new MenuItemBasicType(availableLocales: []);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setLabel('');

        $context = $this->createMock(ExecutionContextInterface::class);
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);

        $context->expects(self::once())
            ->method('buildViolation')
            ->with('form.menu_item_type.label_required')
            ->willReturn($builder);

        $builder->expects(self::once())
            ->method('atPath')
            ->with('label')
            ->willReturn($builder);

        $builder->expects(self::once())
            ->method('setTranslationDomain')
            ->with(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
            ->willReturn($builder);

        $builder->expects(self::once())->method('addViolation');

        $type->validateLabelWhenNotDivider($item, $context);
    }

    public function testMenuItemConfigTypeBuildPermissionKeyChoicesAddsCurrentWhenMissing(): void
    {
        $repo = $this->createMock(MenuItemRepository::class);
        $repo->expects(self::never())->method('getPossibleParentsQueryBuilder');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(static fn (string $id, array $params = [], ?string $domain = null): string => $id);

        $type = new MenuItemConfigType(
            menuItemRepository: $repo,
            permissionKeyChoices: ['authenticated', 'admin'],
            defaultLocale: 'en',
            translator: $translator,
        );

        $menuItem = new MenuItem();
        $menuItem->setPermissionKey('path:/');

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, $menuItem, routeParamsFormTransformer: true);

        $type->buildForm($builder, [
            'app_routes'  => [],
            'menu'        => null,
            'exclude_ids' => [],
            'locale'      => 'en',
        ]);

        $pc = $this->findAddCall($addCalls, 'permissionKey');
        self::assertSame(ChoiceType::class, $pc['type']);

        $choices = $pc['choices'] ?? [];
        self::assertArrayHasKey('authenticated', $choices);
        self::assertArrayHasKey('admin', $choices);
        self::assertArrayHasKey('path:/ (current)', $choices);
        self::assertSame('path:/', $choices['path:/ (current)']);
    }

    public function testMenuItemConfigTypeConfigureOptionsSetsDefaultsAndAllowedTypes(): void
    {
        $type = new MenuItemConfigType(
            menuItemRepository: $this->createStub(MenuItemRepository::class),
            permissionKeyChoices: ['authenticated'],
            defaultLocale: 'en',
        );

        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([]);
        self::assertSame(MenuItem::class, $options['data_class']);
        self::assertSame([], $options['app_routes']);
        self::assertNull($options['menu']);
        self::assertSame([], $options['exclude_ids']);
        self::assertSame('en', $options['locale']);
        self::assertSame(NowoDashboardMenuBundle::TRANSLATION_DOMAIN, $options['translation_domain']);

        $optionsOverride = $resolver->resolve([
            'app_routes'  => ['app_page' => ['label' => 'Page', 'params' => []]],
            'menu'        => null,
            'exclude_ids' => [1, 2],
            'locale'      => 'fr',
        ]);
        self::assertSame(['app_page' => ['label' => 'Page', 'params' => []]], $optionsOverride['app_routes']);
        self::assertSame([1, 2], $optionsOverride['exclude_ids']);
        self::assertSame('fr', $optionsOverride['locale']);

        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $resolver->resolve(['locale' => 123]);
    }

    public function testMenuItemConfigTypeBuildRouteChoiceAttrReturnsJsonParams(): void
    {
        $repo = $this->createStub(MenuItemRepository::class);
        $type = new MenuItemConfigType(
            menuItemRepository: $repo,
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, new MenuItem(), routeParamsFormTransformer: true);

        $type->buildForm($builder, [
            'app_routes' => [
                'app_page' => ['label' => 'Page', 'params' => ['section', 'tab']],
            ],
            'menu'        => null,
            'exclude_ids' => [],
            'locale'      => 'en',
        ]);

        $routeName = $this->findAddCall($addCalls, 'routeName');
        self::assertSame(ChoiceType::class, $routeName['type']);
        $choiceAttr = $routeName['choice_attr'] ?? null;
        self::assertIsCallable($choiceAttr);

        $attr = $choiceAttr(null, 'Page', 'app_page');
        self::assertSame('["section","tab"]', $attr['data-params']);

        $attrUnknown = $choiceAttr(null, 'Unknown', 'unknown_route');
        self::assertSame('[]', $attrUnknown['data-params']);
    }

    public function testMenuItemConfigTypeAddsParentFieldWhenMenuOptionIsMenuInstance(): void
    {
        $menu = new Menu();
        $menu->setCode('menu');

        $qb = $this->createMock(QueryBuilder::class);

        $repo = $this->createMock(MenuItemRepository::class);
        $repo->expects(self::once())
            ->method('getPossibleParentsQueryBuilder')
            ->with($menu, [1, 2])
            ->willReturn($qb);

        $type = new MenuItemConfigType(
            menuItemRepository: $repo,
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, new MenuItem(), routeParamsFormTransformer: true);

        $type->buildForm($builder, [
            'app_routes'  => [],
            'menu'        => $menu,
            'exclude_ids' => [1, 2],
            'locale'      => 'en',
        ]);

        $parentCall = $this->findAddCall($addCalls, 'parent');
        self::assertSame(\Symfony\Bridge\Doctrine\Form\Type\EntityType::class, $parentCall['type']);
        self::assertSame($qb, $parentCall['query_builder']);

        $choiceLabel = $parentCall['choice_label'];
        self::assertIsCallable($choiceLabel);

        $parent = new MenuItem();
        $parent->setLabel('Parent');

        $child = new MenuItem();
        $child->setLabel('Child');
        $child->setParent($parent);

        self::assertSame('Parent > Child', $choiceLabel($child));
    }

    private function createFormBuilderMock(
        array &$addCalls,
        mixed $data = null,
        array &$eventListeners = [],
        bool $routeParamsFormTransformer = false,
        bool $captureEventListeners = false
    ): FormBuilderInterface {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('getData')->willReturn($data);

        $builder->method('add')->willReturnCallback(static function (string $name, $type, array $options = []) use (&$addCalls, $builder): FormBuilderInterface {
            $addCalls[] = ['name' => $name, 'type' => $type, 'options' => $options];

            return $builder;
        });

        if ($routeParamsFormTransformer) {
            $routeParamsForm = $this->createMock(FormBuilderInterface::class);
            $routeParamsForm->expects(self::once())->method('addModelTransformer');
            $builder->method('get')->with('routeParams')->willReturn($routeParamsForm);
        }

        if ($captureEventListeners) {
            $builder->method('addEventListener')->willReturnCallback(static function (string $eventName, callable $listener) use (&$eventListeners, $builder): FormBuilderInterface {
                $eventListeners[$eventName] = $listener;

                return $builder;
            });
        }

        return $builder;
    }

    private function findAddCall(array $addCalls, string $name): ?array
    {
        foreach ($addCalls as $call) {
            if (($call['name'] ?? null) === $name) {
                return array_merge(['type' => $call['type']], $call['options']);
            }
        }

        return null;
    }
}
