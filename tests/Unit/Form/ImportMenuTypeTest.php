<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Nowo\DashboardMenuBundle\Form\ImportMenuType;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImportMenuTypeTest extends TestCase
{
    public function testConfigureOptionsSetsDefaults(): void
    {
        $type     = new ImportMenuType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([]);

        self::assertSame('POST', $options['method']);
        self::assertSame(NowoDashboardMenuBundle::TRANSLATION_DOMAIN, $options['translation_domain']);
    }

    public function testBuildFormAddsFileAndStrategyFieldsWithConstraints(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => 't:' . $id);

        $type     = new ImportMenuType($translator);
        $addCalls = [];
        $builder  = $this->createFormBuilderMock($addCalls);

        $type->buildForm($builder, []);

        $fileCall     = $this->findAdd($addCalls, 'file');
        $strategyCall = $this->findAdd($addCalls, 'strategy');

        self::assertSame(FileType::class, $fileCall['type']);
        self::assertTrue($fileCall['options']['required']);
        self::assertSame('t:form.import_menu_type.file.label', $fileCall['options']['label']);
        self::assertSame(['accept' => '.json,application/json'], $fileCall['options']['attr']);

        $constraints = $fileCall['options']['constraints'] ?? [];
        self::assertCount(2, $constraints);
        self::assertInstanceOf(NotBlank::class, $constraints[0]);
        self::assertInstanceOf(FileConstraint::class, $constraints[1]);

        /** @var FileConstraint $fileConstraint */
        $fileConstraint = $constraints[1];
        self::assertSame(2000000, $fileConstraint->maxSize);
        self::assertSame(['application/json', 'text/plain'], $fileConstraint->mimeTypes);

        self::assertSame(ChoiceType::class, $strategyCall['type']);
        self::assertTrue($strategyCall['options']['required']);
        self::assertSame('t:form.import_menu_type.strategy.label', $strategyCall['options']['label']);
        self::assertSame(MenuImporter::STRATEGY_SKIP_EXISTING, $strategyCall['options']['data']);

        $choices = $strategyCall['options']['choices'] ?? [];
        self::assertArrayHasKey('t:form.import_menu_type.strategy.skip_existing', $choices);
        self::assertSame(MenuImporter::STRATEGY_SKIP_EXISTING, $choices['t:form.import_menu_type.strategy.skip_existing']);
        self::assertSame(
            MenuImporter::STRATEGY_REPLACE,
            $choices['t:form.import_menu_type.strategy.replace'],
        );
    }

    /**
     * @param list<array{name: string, type: mixed, options: array<string, mixed>}> $addCalls
     *
     * @return FormBuilderInterface<mixed>
     */
    private function createFormBuilderMock(array &$addCalls): FormBuilderInterface
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(static function (string $name, $type, array $options = []) use (&$addCalls, $builder): FormBuilderInterface {
            $addCalls[] = ['name' => $name, 'type' => $type, 'options' => $options];

            return $builder;
        });

        return $builder;
    }

    /**
     * @param list<array{name: string, type: mixed, options: array<string, mixed>}> $addCalls
     *
     * @return array{name: string, type: mixed, options: array<string, mixed>}
     */
    private function findAdd(array $addCalls, string $name): array
    {
        foreach ($addCalls as $call) {
            if ($call['name'] === $name) {
                return $call;
            }
        }

        self::fail('Missing add call for ' . $name);
    }
}
