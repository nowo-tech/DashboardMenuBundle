<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Form\MenuType;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function count;

final class MenuTypeTest extends TestCase
{
    public function testConfigureOptions(): void
    {
        $resolver = new OptionsResolver();
        $type     = new MenuType();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([]);
        self::assertSame(Menu::class, $options['data_class']);
        self::assertFalse($options['is_edit']);
        self::assertSame('POST', $options['method']);

        $withAction = $resolver->resolve(['action' => '/save', 'is_edit' => true]);
        self::assertSame('/save', $withAction['action']);
        self::assertTrue($withAction['is_edit']);
    }

    public function testBuildFormWithNoDataAddsAllFields(): void
    {
        $addCalls = [];
        $builder  = $this->createFormBuilderMock(null, $addCalls);
        $type     = new MenuType([], []);
        $type->buildForm($builder, []);
        self::assertGreaterThanOrEqual(10, count($addCalls));
    }

    public function testBuildFormWithMenuEditBaseLocksCode(): void
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $menu->setBase(true);
        $ref = new ReflectionProperty(Menu::class, 'id');
        $ref->setValue($menu, 1);

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($menu, $addCalls);
        $type     = new MenuType([], []);
        $type->buildForm($builder, []);

        $codeOptions = $this->findAddCall($addCalls, 'code');
        self::assertNotNull($codeOptions);
        self::assertTrue($codeOptions['attr']['readonly'] ?? false);
    }

    public function testBuildFormWithPermissionCheckerNotInChoicesAddsCurrentLabel(): void
    {
        $menu = new Menu();
        $menu->setPermissionChecker('custom_checker');

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($menu, $addCalls);
        $type     = new MenuType(['allow_all' => 'Allow all'], []);
        $type->buildForm($builder, []);

        $pcOptions = $this->findAddCall($addCalls, 'permissionChecker');
        self::assertNotNull($pcOptions);
        self::assertArrayHasKey('custom_checker', $pcOptions['choices']);
        self::assertSame('custom_checker (current)', $pcOptions['choices']['custom_checker']);
    }

    public function testBuildFormWithCssClassOptionsUsesChoiceType(): void
    {
        $addCalls = [];
        $builder  = $this->createFormBuilderMock(new Menu(), $addCalls);
        $type     = new MenuType([], ['menu' => ['nav flex-column', 'nav flex-row']]);
        $type->buildForm($builder, []);

        $classMenuOptions = $this->findAddCall($addCalls, 'classMenu');
        self::assertNotNull($classMenuOptions);
        self::assertSame(\Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, $classMenuOptions['type']);
    }

    public function testBuildFormWithEmptyCssClassOptionsUsesTextType(): void
    {
        $addCalls = [];
        $builder  = $this->createFormBuilderMock(new Menu(), $addCalls);
        $type     = new MenuType([], []);
        $type->buildForm($builder, []);

        $classMenuOptions = $this->findAddCall($addCalls, 'classMenu');
        self::assertNotNull($classMenuOptions);
        self::assertSame(\Symfony\Component\Form\Extension\Core\Type\TextType::class, $classMenuOptions['type']);
    }

    public function testBuildFormWithCurrentCssNotInOptionsAddsCurrentChoice(): void
    {
        $menu = new Menu();
        $menu->setClassMenu('custom-nav');

        $addCalls = [];
        $builder  = $this->createFormBuilderMock($menu, $addCalls);
        $type     = new MenuType([], ['menu' => ['nav flex-column']]);
        $type->buildForm($builder, []);

        $classMenuOptions = $this->findAddCall($addCalls, 'classMenu');
        self::assertNotNull($classMenuOptions);
        self::assertArrayHasKey('custom-nav', $classMenuOptions['choices']);
        self::assertSame('custom-nav (current)', $classMenuOptions['choices']['custom-nav']);
    }

    private function createFormBuilderMock(?object $data, array &$addCalls): FormBuilderInterface
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('getData')->willReturn($data);
        $builder->method('add')->willReturnCallback(static function (string $name, $type, array $options = []) use (&$addCalls, $builder): \PHPUnit\Framework\MockObject\MockObject {
            $addCalls[] = ['name' => $name, 'type' => $type, 'options' => $options];

            return $builder;
        });
        $contextBuilder = $this->createMock(FormBuilderInterface::class);
        $contextBuilder->method('addModelTransformer')->willReturnSelf();
        $builder->method('get')->with('context')->willReturn($contextBuilder);

        return $builder;
    }

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
