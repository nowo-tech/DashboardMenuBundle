<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Form\MenuConfigType;
use Nowo\DashboardMenuBundle\Form\MenuDefinitionType;
use Nowo\DashboardMenuBundle\Form\MenuType;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MenuTypeTest extends TestCase
{
    public function testMenuTypeConfigureOptions(): void
    {
        $resolver = new OptionsResolver();
        $type     = new MenuType();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([]);
        self::assertSame(Menu::class, $options['data_class']);
        self::assertFalse($options['is_edit']);
        self::assertSame('POST', $options['method']);
        self::assertNull($options['section']);
    }

    public function testMenuTypeBuildFormAddsDefinitionAndConfigByDefault(): void
    {
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuType();
        $type->buildForm($builder, []);

        $names = array_map(static fn (array $c): string => $c['name'], $addCalls);
        self::assertContains('definition', $names);
        self::assertContains('config', $names);
    }

    public function testMenuTypeBuildFormAddsOnlyDefinitionWhenSectionBasic(): void
    {
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuType();
        $type->buildForm($builder, ['section' => 'basic']);

        $names = array_map(static fn (array $c): string => $c['name'], $addCalls);
        self::assertContains('definition', $names);
        self::assertNotContains('config', $names);
    }

    public function testMenuTypeBuildFormAddsOnlyConfigWhenSectionConfig(): void
    {
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type = new MenuType();
        $type->buildForm($builder, ['section' => 'config']);

        $names = array_map(static fn (array $c): string => $c['name'], $addCalls);
        self::assertNotContains('definition', $names);
        self::assertContains('config', $names);
    }

    public function testMenuDefinitionTypeLocksCodeWhenEditingBaseMenu(): void
    {
        $menu = new Menu();
        $menu->setBase(true);

        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($menu, 1);

        $addCalls    = [];
        $contextForm = $this->createMock(FormBuilderInterface::class);
        $contextForm->expects(self::once())->method('addModelTransformer');

        $builder = $this->createFormBuilderMock($addCalls, $menu, 'context', $contextForm);

        $type = new MenuDefinitionType();
        $type->buildForm($builder, []);

        $code = $this->findAddCall($addCalls, 'code');
        self::assertNotNull($code);
        self::assertTrue($code['attr']['readonly'] ?? false);
    }

    public function testMenuConfigTypePermissionCheckerChoiceAddsCurrentAndCssClassUsesChoiceType(): void
    {
        $menu = new Menu();
        $menu->setPermissionChecker('custom_checker');
        $menu->setClassMenu('custom-nav');

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, $menu);

        $type = new MenuConfigType(
            permissionCheckerChoices: ['allow_all' => 'Allow all'],
            cssClassOptions: [
                'menu' => ['nav flex-column'],
            ],
            translator: null,
        );

        $type->buildForm($builder, []);

        $permissionChecker = $this->findAddCall($addCalls, 'permissionChecker');
        self::assertSame(ChoiceType::class, $permissionChecker['type']);
        self::assertArrayHasKey('custom_checker', $permissionChecker['choices'] ?? []);
        self::assertSame('custom_checker (current)', $permissionChecker['choices']['custom_checker']);

        $classMenu = $this->findAddCall($addCalls, 'classMenu');
        self::assertSame(ChoiceType::class, $classMenu['type']);
        self::assertArrayHasKey('custom-nav', $classMenu['choices'] ?? []);
        self::assertSame('custom-nav (current)', $classMenu['choices']['custom-nav']);
    }

    public function testMenuConfigTypeConfigureOptionsSetsDefaultsAndTranslationDomain(): void
    {
        $resolver = new OptionsResolver();
        $type     = new MenuConfigType(
            permissionCheckerChoices: [],
            cssClassOptions: [],
            translator: null,
        );

        $type->configureOptions($resolver);

        $options = $resolver->resolve([]);
        self::assertSame(Menu::class, $options['data_class']);
        self::assertSame(NowoDashboardMenuBundle::TRANSLATION_DOMAIN, $options['translation_domain']);
    }

    public function testMenuDefinitionTypeConfigureOptionsSetsDefaultsAndTranslationDomain(): void
    {
        $resolver = new OptionsResolver();
        $type     = new MenuDefinitionType();

        $type->configureOptions($resolver);

        $options = $resolver->resolve([]);
        self::assertSame(Menu::class, $options['data_class']);
        self::assertSame(NowoDashboardMenuBundle::TRANSLATION_DOMAIN, $options['translation_domain']);
    }

    public function testMenuConfigTypeUlIdFieldUsesChoiceTypeAndAddsCurrentWhenOptionsPresent(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (mixed $id, array $parameters = [], ?string $domain = null): string => $id . '_translated');

        $menu = new Menu();
        $menu->setUlId('my-ul');

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, $menu);

        $type = new MenuConfigType(
            permissionCheckerChoices: [],
            cssClassOptions: [],
            ulIdOptions: ['a' => 'a', 'b' => 'b'],
            translator: $translator,
        );

        $type->buildForm($builder, []);

        $ulId = $this->findAddCall($addCalls, 'ulId');
        self::assertNotNull($ulId);
        self::assertSame(ChoiceType::class, $ulId['type']);
        self::assertArrayHasKey('my-ul', $ulId['choices'] ?? []);
        self::assertSame('my-ul (current)', $ulId['choices']['my-ul'] ?? null);

        self::assertFalse($ulId['choice_translation_domain'] ?? null);
    }

    public function testMenuConfigTypeUlIdFieldUsesTextTypeAndTranslatesPlaceholderWhenOptionsEmpty(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('ul_id_placeholder_translated');

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, new Menu());

        $type = new MenuConfigType(
            permissionCheckerChoices: [],
            cssClassOptions: [],
            ulIdOptions: [],
            translator: $translator,
        );

        $type->buildForm($builder, []);

        $ulId = $this->findAddCall($addCalls, 'ulId');
        self::assertNotNull($ulId);
        self::assertSame(TextType::class, $ulId['type']);
        self::assertSame('ul_id_placeholder_translated', $ulId['attr']['placeholder'] ?? null);
    }

    public function testMenuConfigTypeUlIdFieldPlaceholderFallsBackToEmptyKeyWhenNoTranslator(): void
    {
        $menu = new Menu();
        $menu->setUlId('my-ul');

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls, $menu);

        $type = new MenuConfigType(
            permissionCheckerChoices: [],
            cssClassOptions: [],
            ulIdOptions: ['a' => 'a', 'b' => 'b'],
        );

        $type->buildForm($builder, []);

        $ulId = $this->findAddCall($addCalls, 'ulId');
        self::assertNotNull($ulId);
        self::assertSame(ChoiceType::class, $ulId['type']);
        self::assertSame('form.menu_type.empty_choice', $ulId['placeholder'] ?? null);
    }

    private function createFormBuilderMock(
        array &$addCalls,
        mixed $data = null,
        ?string $getKey = null,
        ?FormBuilderInterface $returnForm = null
    ): FormBuilderInterface {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('getData')->willReturn($data);
        $builder->method('add')->willReturnCallback(static function (string $name, $type, array $options = []) use (&$addCalls, $builder): FormBuilderInterface {
            $addCalls[] = ['name' => $name, 'type' => $type, 'options' => $options];

            return $builder;
        });

        if ($getKey !== null && $returnForm instanceof FormBuilderInterface) {
            $builder->method('get')->with($getKey)->willReturn($returnForm);
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
