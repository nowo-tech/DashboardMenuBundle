<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Nowo\DashboardMenuBundle\Form\SortableTreeType;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SortableTreeTypeTest extends TestCase
{
    public function testBuildFormAddsTreePayloadField(): void
    {
        $addCalls = [];
        $builder  = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(static function (string $name, $type, array $options = []) use (&$addCalls, $builder): FormBuilderInterface {
            $addCalls[] = ['name' => $name, 'type' => $type, 'options' => $options];

            return $builder;
        });

        $type = new SortableTreeType();
        $type->buildForm($builder, []);

        self::assertCount(1, $addCalls);
        self::assertSame('tree', $addCalls[0]['name']);
        self::assertSame(HiddenType::class, $addCalls[0]['type']);
        self::assertFalse($addCalls[0]['options']['required']);
    }

    public function testConfigureOptionsAndBlockPrefix(): void
    {
        $type     = new SortableTreeType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([
            'action'        => '/admin/menus/1/items/reorder-tree',
            'csrf_token_id' => 'reorder_tree_1',
        ]);

        self::assertSame('_token', $options['csrf_field_name']);
        self::assertTrue($options['csrf_protection']);
        self::assertSame('POST', $options['method']);
        self::assertSame(NowoDashboardMenuBundle::TRANSLATION_DOMAIN, $options['translation_domain']);
        self::assertSame('', $type->getBlockPrefix());
    }
}
