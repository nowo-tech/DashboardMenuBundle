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
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;

final class MenuItemTypeTest extends TestCase
{
    public function testMenuItemTypeConfigureOptions(): void
    {
        $this->createStub(MenuItemRepository::class);
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
        self::assertNull($options['section'] ?? null);
        self::assertSame('POST', $options['method']);
    }

    public function testMenuItemTypeBuildFormAddsBasicAndConfigByDefault(): void
    {
        $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuItemType();
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
        $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuItemType();
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
        $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuItemType();
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

    public function testMenuItemTypeBuildFormAddsBasicIconAndConfigWhenSectionIdentity(): void
    {
        $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuItemType();
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
            'section'           => 'identity',
        ]);

        $names = array_map(static fn (array $c): string => $c['name'], $addCalls);
        self::assertContains('basic', $names);
        self::assertContains('icon', $names);
        self::assertContains('config', $names);
    }

    public function testMenuItemTypeBuildFormAddsBasicIconWithoutConfigWhenSectionMinimal(): void
    {
        $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuItemType();
        $type->buildForm($builder, [
            'app_routes'           => [],
            'available_locales'    => [],
            'menu'                 => null,
            'exclude_ids'          => [],
            'locale'               => 'en',
            'section'              => 'minimal',
            'include_translations' => false,
        ]);

        $names = array_map(static fn (array $c): string => $c['name'], $addCalls);
        self::assertContains('basic', $names);
        self::assertContains('icon', $names);
        self::assertNotContains('config', $names);

        $iconCall = $this->findAddCall($addCalls, 'icon');
        self::assertNotNull($iconCall);
        self::assertTrue($iconCall['item_type_only'] ?? false);
    }

    public function testMenuItemTypeBuildFormAddsIconOnlyWhenSectionIcon(): void
    {
        $this->createStub(MenuItemRepository::class);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuItemType();
        $type->buildForm($builder, [
            'app_routes'        => [],
            'available_locales' => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
            'section'           => 'icon',
        ]);

        $names = array_map(static fn (array $c): string => $c['name'], $addCalls);
        self::assertNotContains('basic', $names);
        self::assertContains('icon', $names);
        self::assertNotContains('config', $names);
    }

    public function testMenuItemBasicTypeBuildFormAddsLabelField(): void
    {
        $availableLocales = [];
        $type             = new MenuItemBasicType(availableLocales: $availableLocales);

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, new MenuItem());

        $type->buildForm($builder, ['available_locales' => $availableLocales]);

        $labelCall = $this->findAddCall($addCalls, 'label');
        self::assertNotNull($labelCall);
        self::assertSame(TextType::class, $labelCall['type']);

        // type + position are now edited in MenuItemIconType
        self::assertNull($this->findAddCall($addCalls, 'itemType'));
        self::assertNull($this->findAddCall($addCalls, 'position'));
    }

    // Icon editing lives in MenuItemIconType now (icon-only partial).

    public function testMenuItemBasicTypeAddsLocaleFieldsForDividerWithoutPreSubmitClear(): void
    {
        $availableLocales = ['en', 'es'];

        $menuItem = new MenuItem();
        $menuItem->setTranslations(['en' => 'Home']);
        $menuItem->setItemType(MenuItem::ITEM_TYPE_DIVIDER);

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
        self::assertArrayNotHasKey(FormEvents::PRE_SUBMIT, $eventListeners);
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

        $formRoot = $this->createMock(FormInterface::class);
        $formRoot->method('getData')->willReturn($menuItem);
        $form->method('getRoot')->willReturn($formRoot);

        $event = $this->createMock(FormEvent::class);
        $event->method('getData')->willReturn(new stdClass());
        $event->method('getForm')->willReturn($form);

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

        $formRoot = $this->createMock(FormInterface::class);
        $formRoot->method('getData')->willReturn($menuItem);
        $form->method('getRoot')->willReturn($formRoot);

        $event = $this->createMock(FormEvent::class);
        $event->method('getData')->willReturn(new stdClass());
        $event->method('getForm')->willReturn($form);

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

        $form     = $this->createMock(FormInterface::class);
        $formRoot = $this->createMock(FormInterface::class);
        $formRoot->method('getData')->willReturn(new stdClass());
        $form->method('getRoot')->willReturn($formRoot);

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

    public function testMenuItemBasicTypeDoesNotRegisterPreSubmitListener(): void
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

        self::assertArrayNotHasKey(FormEvents::PRE_SUBMIT, $eventListeners);
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

    public function testMenuItemBasicTypeValidateLabelWhenBaseLabelNonEmptyReturnsEarly(): void
    {
        $type = new MenuItemBasicType(availableLocales: []);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setLabel('Base label');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        $type->validateLabelWhenNotDivider($item, $context);
    }

    public function testMenuItemBasicTypeValidateLabelWhenTranslationsHasNonEmptyStringReturnsEarly(): void
    {
        $type = new MenuItemBasicType(availableLocales: []);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setLabel('');
        $item->setTranslations(['en' => 'Home']);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        $type->validateLabelWhenNotDivider($item, $context);
    }

    public function testMenuItemBasicTypeFinishViewReturnsEarlyWhenAvailableLocalesEmpty(): void
    {
        $type = new MenuItemBasicType(availableLocales: ['en']);

        $view           = new FormView();
        $view->children = [];

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::never())->method('getData');

        $type->finishView($view, $form, ['available_locales' => []]);
    }

    public function testMenuItemBasicTypeFinishViewReturnsEarlyWhenFormDataIsNotMenuItem(): void
    {
        $type = new MenuItemBasicType(availableLocales: ['en']);

        $view           = new FormView();
        $view->children = [];

        $form = $this->createMock(FormInterface::class);
        $form->method('getData')->willReturn(new stdClass());
        $form->expects(self::never())->method('has');

        $type->finishView($view, $form, ['available_locales' => ['en']]);

        self::assertSame([], $view->children);
    }

    public function testMenuItemBasicTypeFinishViewHydratesLocaleLabelFieldsUsingFormOrTranslations(): void
    {
        $type = new MenuItemBasicType(availableLocales: ['en', 'es']);

        $menuItem = new MenuItem();
        $menuItem->setTranslations(['en' => 'FromEntity', 'es' => 'FromEntityEs']);

        $view                             = new FormView();
        $view->children                   = [];
        $view->children['label_en']       = new FormView();
        $view->children['label_en']->vars = [];
        $view->children['label_es']       = new FormView();
        $view->children['label_es']->vars = [];

        $fieldEn = $this->createMock(FormInterface::class);
        $fieldEn->method('getData')->willReturn('FromForm');

        // Empty current value should fall back to entity translations.
        $fieldEs = $this->createMock(FormInterface::class);
        $fieldEs->method('getData')->willReturn('');

        $form = $this->createMock(FormInterface::class);
        $form->method('getData')->willReturn($menuItem);
        $form->method('has')->willReturnCallback(static fn (string $name): bool => in_array($name, ['label_en', 'label_es'], true));
        $form->method('get')->willReturnCallback(static fn (string $name): FormInterface => match ($name) {
            'label_en' => $fieldEn,
            'label_es' => $fieldEs,
            default    => $fieldEn,
        });

        $type->finishView($view, $form, ['available_locales' => ['en', 'es']]);

        self::assertSame('FromForm', $view->children['label_en']->vars['value']);
        self::assertSame('FromEntityEs', $view->children['label_es']->vars['value']);
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
        $menuItem->setPermissionKeys(['path:/']);

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, $menuItem, routeParamsFormTransformer: true);

        $type->buildForm($builder, [
            'app_routes'  => [],
            'menu'        => null,
            'exclude_ids' => [],
            'locale'      => 'en',
        ]);

        $pc = $this->findAddCall($addCalls, 'permissionKeys');
        self::assertNotNull($pc);
        self::assertSame(ChoiceType::class, $pc['type']);

        $choices = $pc['choices'] ?? [];
        self::assertArrayHasKey('authenticated', $choices);
        self::assertArrayHasKey('admin', $choices);
        self::assertArrayHasKey('path:/ (current)', $choices);
        self::assertSame('path:/', $choices['path:/ (current)']);

        $unanimous = $this->findAddCall($addCalls, 'isUnanimous');
        self::assertNotNull($unanimous);
        self::assertSame(CheckboxType::class, $unanimous['type']);
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
        self::assertNull($options['item_form_section']);
        self::assertNull($options['menu_item']);
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
        self::assertNotNull($routeName);
        self::assertSame(ChoiceType::class, $routeName['type']);
        $choiceAttr = $routeName['choice_attr'] ?? null;
        self::assertIsCallable($choiceAttr);

        $attr = $choiceAttr(null, 'Page', 'app_page');
        self::assertSame('["section","tab"]', $attr['data-params']);

        $attrUnknown = $choiceAttr(null, 'Unknown', 'unknown_route');
        self::assertSame('[]', $attrUnknown['data-params']);
    }

    public function testMenuItemConfigTypeConfigSectionServiceOmitsClassicLinkFields(): void
    {
        $repo = $this->createStub(MenuItemRepository::class);
        $type = new MenuItemConfigType(
            menuItemRepository: $repo,
            permissionKeyChoices: [],
            menuLinkResolverChoices: ['App\\DemoResolver' => 'Demo resolver'],
            defaultLocale: 'en',
        );

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('App\\DemoResolver');

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, $item);

        $type->buildForm($builder, [
            'app_routes'        => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
            'item_form_section' => 'config',
        ]);

        self::assertNull($this->findAddCall($addCalls, 'linkType'));
        self::assertNull($this->findAddCall($addCalls, 'routeName'));
        self::assertNull($this->findAddCall($addCalls, 'externalUrl'));
        self::assertNull($this->findAddCall($addCalls, 'routeParams'));
        self::assertNotNull($this->findAddCall($addCalls, 'targetBlank'));
        self::assertNotNull($this->findAddCall($addCalls, 'linkResolver'));
    }

    /**
     * Same entity as inherit_data, but child FormBuilder::getData() is null during buildForm (Symfony).
     */
    public function testMenuItemConfigTypeUsesMenuItemOptionWhenBuilderDataIsNull(): void
    {
        $repo = $this->createStub(MenuItemRepository::class);
        $type = new MenuItemConfigType(
            menuItemRepository: $repo,
            permissionKeyChoices: [],
            menuLinkResolverChoices: ['App\\DemoResolver' => 'Demo resolver'],
            defaultLocale: 'en',
        );

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('App\\DemoResolver');

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type->buildForm($builder, [
            'app_routes'        => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
            'item_form_section' => 'config',
            'menu_item'         => $item,
        ]);

        self::assertNotNull($this->findAddCall($addCalls, 'linkResolver'));
        self::assertNull($this->findAddCall($addCalls, 'linkType'));
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
        self::assertNotNull($parentCall);
        self::assertSame(\Symfony\Bridge\Doctrine\Form\Type\EntityType::class, $parentCall['type']);
        $parentQb = $parentCall['query_builder'];
        self::assertIsCallable($parentQb);
        self::assertSame($qb, $parentQb($repo));

        $choiceLabel = $parentCall['choice_label'];
        self::assertIsCallable($choiceLabel);

        $parent = new MenuItem();
        $parent->setLabel('Parent');

        $child = new MenuItem();
        $child->setLabel('Child');
        $child->setParent($parent);

        self::assertSame('Parent > Child', $choiceLabel($child));
    }

    public function testMenuItemConfigTypeMergesExcludeIdsWithSubtreeWhenItemHasId(): void
    {
        $menu = new Menu();
        $menu->setCode('menu');

        $qb = $this->createMock(QueryBuilder::class);

        $editing = new MenuItem();
        $editing->setMenu($menu);
        $editing->setLabel('Edit me');
        $ref = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($editing, 10);

        $repo = $this->createMock(MenuItemRepository::class);
        $repo->expects(self::once())
            ->method('findIdsInSubtreeStartingAt')
            ->with($menu, 10)
            ->willReturn([10, 11, 12]);
        $repo->expects(self::once())
            ->method('getPossibleParentsQueryBuilder')
            ->with($menu, [1, 10, 11, 12])
            ->willReturn($qb);

        $type = new MenuItemConfigType(
            menuItemRepository: $repo,
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, $editing, routeParamsFormTransformer: true);

        $type->buildForm($builder, [
            'app_routes'  => [],
            'menu'        => $menu,
            'exclude_ids' => [1],
            'locale'      => 'en',
        ]);

        $parentCall = $this->findAddCall($addCalls, 'parent');
        self::assertNotNull($parentCall);
        self::assertIsCallable($parentCall['query_builder']);
        self::assertSame($qb, $parentCall['query_builder']($repo));
    }

    public function testMenuItemConfigTypeValidateParentNoCircularDirectSelfViolation(): void
    {
        $type = new MenuItemConfigType(
            menuItemRepository: $this->createStub(MenuItemRepository::class),
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $item = new MenuItem();
        $item->setParent($item);

        $context = $this->createMock(ExecutionContextInterface::class);
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $context->expects(self::once())
            ->method('buildViolation')
            ->with('form.menu_item_type.parent.circular_violation')
            ->willReturn($builder);
        $builder->method('atPath')->with('parent')->willReturn($builder);
        $builder->method('setTranslationDomain')->with(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)->willReturn($builder);
        $builder->expects(self::once())->method('addViolation');

        $type->validateParentNoCircular($item, $context);
    }

    public function testMenuItemConfigTypeValidateParentNoCircularNoViolationWhenNoParent(): void
    {
        $type = new MenuItemConfigType(
            menuItemRepository: $this->createStub(MenuItemRepository::class),
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $item = new MenuItem();
        $item->setParent(null);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        $type->validateParentNoCircular($item, $context);
    }

    public function testMenuItemConfigTypeValidateSectionMustBeRootRejectsWhenParentSet(): void
    {
        $type = new MenuItemConfigType(
            menuItemRepository: $this->createStub(MenuItemRepository::class),
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $parent  = new MenuItem();
        $section = new MenuItem();
        $section->setItemType(MenuItem::ITEM_TYPE_SECTION);
        $section->setParent($parent);

        $context = $this->createMock(ExecutionContextInterface::class);
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $context->expects(self::once())
            ->method('buildViolation')
            ->with('form.menu_item_type.parent.section_must_be_root')
            ->willReturn($builder);
        $builder->method('atPath')->with('parent')->willReturn($builder);
        $builder->method('setTranslationDomain')->with(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)->willReturn($builder);
        $builder->expects(self::once())->method('addViolation');

        $type->validateSectionMustBeRoot($section, $context);
    }

    public function testMenuItemConfigTypeValidateSectionMustBeRootSkipsWhenRootOrNotSection(): void
    {
        $type = new MenuItemConfigType(
            menuItemRepository: $this->createStub(MenuItemRepository::class),
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $section = new MenuItem();
        $section->setItemType(MenuItem::ITEM_TYPE_SECTION);
        $section->setParent(null);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');
        $type->validateSectionMustBeRoot($section, $context);

        $link = new MenuItem();
        $link->setItemType(MenuItem::ITEM_TYPE_LINK);
        $link->setParent(new MenuItem());
        $type->validateSectionMustBeRoot($link, $context);
    }

    public function testMenuItemConfigTypeValidateParentNoCircularRejectsWhenParentIdIsInDbSubtree(): void
    {
        $menu = new Menu();
        $menu->setCode('m');

        $repo = $this->createMock(MenuItemRepository::class);
        $repo->expects(self::once())
            ->method('findIdsInSubtreeStartingAt')
            ->with($menu, 5)
            ->willReturn([5, 6, 7]);

        $type = new MenuItemConfigType(
            menuItemRepository: $repo,
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $item = new MenuItem();
        $item->setMenu($menu);
        $parent = new MenuItem();
        $item->setParent($parent);
        $ref = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($item, 5);
        $ref->setValue($parent, 6);

        $context = $this->createMock(ExecutionContextInterface::class);
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $context->expects(self::once())
            ->method('buildViolation')
            ->with('form.menu_item_type.parent.circular_violation')
            ->willReturn($builder);
        $builder->method('atPath')->with('parent')->willReturn($builder);
        $builder->method('setTranslationDomain')->with(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)->willReturn($builder);
        $builder->expects(self::once())->method('addViolation');

        $type->validateParentNoCircular($item, $context);
    }

    public function testMenuItemConfigTypeValidateParentNoCircularDetectsSameIdDetachedObjects(): void
    {
        $type = new MenuItemConfigType(
            menuItemRepository: $this->createStub(MenuItemRepository::class),
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $item   = new MenuItem();
        $parent = new MenuItem();
        $ref    = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($item, 10);
        $ref->setValue($parent, 10);
        $item->setParent($parent);

        $context = $this->createMock(ExecutionContextInterface::class);
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $context->expects(self::once())
            ->method('buildViolation')
            ->with('form.menu_item_type.parent.circular_violation')
            ->willReturn($builder);
        $builder->method('atPath')->with('parent')->willReturn($builder);
        $builder->method('setTranslationDomain')->with(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)->willReturn($builder);
        $builder->expects(self::once())->method('addViolation');

        $type->validateParentNoCircular($item, $context);
    }

    public function testMenuItemConfigTypeValidateParentNoCircularDetectsAncestorLoop(): void
    {
        $type = new MenuItemConfigType(
            menuItemRepository: $this->createStub(MenuItemRepository::class),
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $item   = new MenuItem();
        $parent = new MenuItem();
        $item->setParent($parent);
        // Existing corrupt chain in DB: parent points back to item.
        $parent->setParent($item);

        $context = $this->createMock(ExecutionContextInterface::class);
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $context->expects(self::once())
            ->method('buildViolation')
            ->with('form.menu_item_type.parent.circular_violation')
            ->willReturn($builder);
        $builder->method('atPath')->with('parent')->willReturn($builder);
        $builder->method('setTranslationDomain')->with(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)->willReturn($builder);
        $builder->expects(self::once())->method('addViolation');

        $type->validateParentNoCircular($item, $context);
    }

    public function testMenuItemConfigTypeValidateParentNoCircularReturnsWhenVisitedParentLoopDetected(): void
    {
        $type = new MenuItemConfigType(
            menuItemRepository: $this->createStub(MenuItemRepository::class),
            permissionKeyChoices: [],
            defaultLocale: 'en',
        );

        $item   = new MenuItem();
        $parent = new MenuItem();
        $grand  = new MenuItem();

        $ref = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($parent, 10);
        $ref->setValue($grand, 10);

        $item->setParent($parent);
        $parent->setParent($grand);
        $grand->setParent(null);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects(self::never())->method('buildViolation');

        $type->validateParentNoCircular($item, $context);
    }

    /**
     * @param list<array{name: string, type: mixed, options: array<string, mixed>}> $addCalls
     * @param array<string, callable(FormEvent): void> $eventListeners
     *
     * @return FormBuilderInterface<mixed>
     */
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
            $permissionKeysForm = $this->createMock(FormBuilderInterface::class);
            $permissionKeysForm->expects(self::atMost(1))->method('addModelTransformer');
            $builder->method('get')->willReturnCallback(static fn (string $name): FormBuilderInterface => match ($name) {
                'routeParams'    => $routeParamsForm,
                'permissionKeys' => $permissionKeysForm,
                default          => $routeParamsForm,
            });
        }

        if ($captureEventListeners) {
            $builder->method('addEventListener')->willReturnCallback(static function (string $eventName, callable $listener) use (&$eventListeners, $builder): FormBuilderInterface {
                $eventListeners[$eventName] = $listener;

                return $builder;
            });
        }

        return $builder;
    }

    public function testParentChoiceBreadcrumbLabelStopsOnParentCycle(): void
    {
        $a   = new MenuItem();
        $b   = new MenuItem();
        $ref = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($a, 1);
        $ref->setValue($b, 2);
        $a->setLabel('A');
        $b->setLabel('B');
        $a->setParent($b);
        $b->setParent($a);

        $method = new ReflectionMethod(MenuItemConfigType::class, 'parentChoiceBreadcrumbLabel');
        $out = $method->invoke(null, $a, 'en');

        self::assertStringContainsString('…', $out);
        self::assertStringContainsString('A', $out);
        self::assertStringContainsString('B', $out);
    }

    /**
     * @param list<array{name: string, type: mixed, options: array<string, mixed>}> $addCalls
     *
     * @return array<string, mixed>|null
     */
    private function findAddCall(array $addCalls, string $name): ?array
    {
        foreach ($addCalls as $call) {
            if ($call['name'] === $name) {
                return array_merge(['type' => $call['type']], $call['options']);
            }
        }

        return null;
    }
}
