<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Nowo\DashboardMenuBundle\Form\CopyMenuType;
use Nowo\DashboardMenuBundle\Tests\Unit\Form\FormKitMergerTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CopyMenuTypeTest extends TestCase
{
    use FormKitMergerTestTrait;

    public function testBuildFormAddsCodeAndNameWithTranslator(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        $addCalls = [];
        $builder  = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(static function (string $name, $type, array $options = []) use (&$addCalls, $builder): \PHPUnit\Framework\MockObject\MockObject {
            $addCalls[] = ['name' => $name, 'options' => $options];

            return $builder;
        });

        $type = new CopyMenuType($translator);
        $this->injectFormKitMerger($type);
        $type->buildForm($builder, []);

        self::assertCount(2, $addCalls);
        self::assertSame('code', $addCalls[0]['name']);
        self::assertTrue($addCalls[0]['options']['required']);
        self::assertSame('form.copy_menu_type.code.label', $addCalls[0]['options']['label']);
        self::assertSame('name', $addCalls[1]['name']);
    }

    public function testBuildFormWithNullTranslatorUsesIdAsPlaceholder(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->expects(self::atLeastOnce())->method('add');

        $type = new CopyMenuType();
        $this->injectFormKitMerger($type);
        $type->buildForm($builder, []);
    }

    public function testConfigureOptions(): void
    {
        $resolver = new OptionsResolver();
        $type     = new CopyMenuType();
        $this->injectFormKitMerger($type);
        $type->configureOptions($resolver);

        $options = $resolver->resolve([]);
        self::assertNull($options['data_class']);
        self::assertSame('POST', $options['method']);

        $optionsWithAction = $resolver->resolve(['action' => '/copy']);
        self::assertSame('/copy', $optionsWithAction['action']);
    }
}
