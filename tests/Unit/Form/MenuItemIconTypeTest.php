<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Form;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\MenuItemIconType;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

use function class_exists;

final class MenuItemIconTypeTest extends TestCase
{
    private function createBuilderMockWithCaptures(mixed $data, array &$addCalls, array &$eventListeners): FormBuilderInterface
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $builder->method('getData')->willReturn($data);

        $builder->method('add')->willReturnCallback(static function (string $name, $type, array $options) use (&$addCalls, $builder): FormBuilderInterface {
            $addCalls[] = ['name' => $name, 'type' => $type, 'options' => $options];

            return $builder;
        });

        $builder->method('addEventListener')->willReturnCallback(static function (string $eventName, callable $listener) use (&$eventListeners, $builder): FormBuilderInterface {
            $eventListeners[$eventName] = $listener;

            return $builder;
        });

        return $builder;
    }

    public function testConfigureOptionsSetsDefaultsAndTranslationDomain(): void
    {
        $resolver = new MenuIconNameResolver();
        $type     = new MenuItemIconType($resolver);

        $optionsResolver = new OptionsResolver();
        $type->configureOptions($optionsResolver);

        $options = $optionsResolver->resolve([]);
        self::assertSame(MenuItem::class, $options['data_class']);
        self::assertSame(NowoDashboardMenuBundle::TRANSLATION_DOMAIN, $options['translation_domain']);
    }

    public function testBuildFormAddsIconSelectorFieldAndNormalizesPositionOnPreSubmit(): void
    {
        if (!class_exists('Nowo\\IconSelectorBundle\\Form\\IconSelectorType')) {
            // Minimal stub for this test environment.
            eval('namespace Nowo\\IconSelectorBundle\\Form; class IconSelectorType { public const MODE_TOM_SELECT = "tom_select"; }');
        }

        $item = new MenuItem();
        $item->setIcon('bootstrap-icons:house');

        $iconResolver = new MenuIconNameResolver(['bootstrap-icons' => 'bi']);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(static fn (string $id, array $params = [], ?string $domain = null): string => $id);

        $addCalls       = [];
        $eventListeners = [];
        $builder        = $this->createBuilderMockWithCaptures($item, $addCalls, $eventListeners);

        $type = new MenuItemIconType($iconResolver, $translator);
        $type->buildForm($builder, []);

        $iconCall = null;
        foreach ($addCalls as $c) {
            if ($c['name'] === 'icon') {
                $iconCall = $c;
                break;
            }
        }

        self::assertNotNull($iconCall);
        self::assertSame(\Nowo\IconSelectorBundle\Form\IconSelectorType::class, $iconCall['type']);
        self::assertContains($iconCall['options']['constraints'] ?? null, [null, []]);
        self::assertContains($iconCall['options']['mapped'] ?? null, [null, true]);
        self::assertContains($iconCall['options']['required'] ?? null, [null, false]);
        self::assertContains($iconCall['options']['data'] ?? null, [null, 'bi:house']);

        self::assertArrayHasKey(FormEvents::PRE_SUBMIT, $eventListeners);
        $listener = $eventListeners[FormEvents::PRE_SUBMIT];

        // When data is not an array => early return, no setData.
        $eventNotArray = $this->createMock(FormEvent::class);
        $eventNotArray->method('getData')->willReturn(new stdClass());
        $eventNotArray->expects(self::never())->method('setData');
        $listener($eventNotArray);

        // When `position` key is missing => do nothing (do not overwrite entity value).
        $eventMissingPosition = $this->createMock(FormEvent::class);
        $eventMissingPosition->method('getData')->willReturn([
            'itemType' => MenuItem::ITEM_TYPE_LINK,
        ]);
        $eventMissingPosition->expects(self::never())->method('setData');
        $listener($eventMissingPosition);

        // When `position` is null => normalize to 0.
        $eventNullPosition = $this->createMock(FormEvent::class);
        $eventNullPosition->method('getData')->willReturn([
            'position' => null,
        ]);
        $eventNullPosition->expects(self::once())->method('setData')->with([
            'position' => 0,
        ]);
        $listener($eventNullPosition);

        // When `position` is empty string => normalize to 0.
        $eventEmptyPosition = $this->createMock(FormEvent::class);
        $eventEmptyPosition->method('getData')->willReturn([
            'position' => '',
        ]);
        $eventEmptyPosition->expects(self::once())->method('setData')->with([
            'position' => 0,
        ]);
        $listener($eventEmptyPosition);
    }

    /**
     * @runInSeparateProcess
     */
    public function testBuildFormAddsTextIconFieldWhenIconSelectorBundleIsMissing(): void
    {
        // If the stub class from other tests is not available in this process,
        // we exercise the fallback branch.
        self::assertFalse(class_exists('Nowo\\IconSelectorBundle\\Form\\IconSelectorType'));

        $item = new MenuItem();
        $item->setIcon('bootstrap-icons:house');

        $iconResolver = new MenuIconNameResolver(['bootstrap-icons' => 'bi']);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(static fn (string $id, array $params = [], ?string $domain = null): string => $id);

        $addCalls       = [];
        $eventListeners = [];
        $builder        = $this->createBuilderMockWithCaptures($item, $addCalls, $eventListeners);

        $type = new MenuItemIconType($iconResolver, $translator);
        $type->buildForm($builder, []);

        $iconCall = null;
        foreach ($addCalls as $c) {
            if ($c['name'] === 'icon') {
                $iconCall = $c;
                break;
            }
        }

        self::assertNotNull($iconCall);
        self::assertSame(TextType::class, $iconCall['type']);
        self::assertArrayHasKey('translation_domain', $iconCall['options']);
        self::assertSame(NowoDashboardMenuBundle::TRANSLATION_DOMAIN, $iconCall['options']['translation_domain']);
    }
}
