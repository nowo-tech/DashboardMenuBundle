<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Nowo\DashboardMenuBundle\Form\SearchQueryType;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Tests\Unit\Form\FormKitMergerTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SearchQueryTypeTest extends TestCase
{
    use FormKitMergerTestTrait;

    public function testBuildFormAddsSearchField(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 't:' . $id);

        $addCalls = [];
        $builder  = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(static function (string $name, $type, array $options = []) use (&$addCalls, $builder): FormBuilderInterface {
            $addCalls[] = ['name' => $name, 'type' => $type, 'options' => $options];

            return $builder;
        });

        $type = new SearchQueryType($translator);
        $this->injectFormKitMerger($type);
        $type->buildForm($builder, []);

        self::assertCount(1, $addCalls);
        self::assertSame('q', $addCalls[0]['name']);
        self::assertSame(SearchType::class, $addCalls[0]['type']);
        self::assertFalse($addCalls[0]['options']['required']);
        self::assertNotSame('', (string) ($addCalls[0]['options']['attr']['placeholder'] ?? ''));
        self::assertSame('t:dashboard.search', $addCalls[0]['options']['attr']['aria-label']);
    }

    public function testConfigureOptionsAndBlockPrefix(): void
    {
        $type = new SearchQueryType();
        $this->injectFormKitMerger($type);
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve(['action' => '/admin/menus']);

        self::assertFalse($options['csrf_protection']);
        self::assertSame('GET', $options['method']);
        self::assertSame(NowoDashboardMenuBundle::TRANSLATION_DOMAIN, $options['translation_domain']);
        self::assertSame('', $type->getBlockPrefix());
    }
}
