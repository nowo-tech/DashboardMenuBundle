<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Form;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\MenuItemConfigType;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * REQ-TEST-003: the class-level validators of the item config form (parent cycles, sections at the root, services need a
 * resolver) and the breadcrumb of the parent dropdown on a corrupt tree.
 */
final class MenuItemConfigTypeValidationTest extends TestCase
{
    public function testAParentCycleInTheStoredTreeStopsTheWalk(): void
    {
        $a = $this->item(1);
        $b = $this->item(2);
        $c = $this->item(3);
        $a->setParent($b);
        $b->setParent($a);
        $c->setParent($a);
        $violations = 0;

        $this->type()->validateParentNoCircular($c, $this->context($violations));

        self::assertSame(0, $violations, 'the cycle does not involve the item itself: no violation, and no endless loop');
    }

    public function testAParentThatLeadsBackToTheItemIsAViolation(): void
    {
        $item   = $this->item(1);
        $parent = $this->item(2);
        $item->setParent($parent);
        $parent->setParent($item);
        $violations = 0;

        $this->type()->validateParentNoCircular($item, $this->context($violations));

        self::assertSame(1, $violations);
    }

    public function testAnItemWithoutAnIdWhoseAncestorIsItselfIsAViolation(): void
    {
        $item   = new MenuItem();
        $parent = $this->item(5);
        $parent->setParent($item);
        $item->setParent($parent);
        $violations = 0;

        $this->type()->validateParentNoCircular($item, $this->context($violations));

        self::assertSame(1, $violations);
    }

    public function testASectionBelowAnotherItemAndAServiceWithoutAResolverAreViolations(): void
    {
        $section = new MenuItem();
        $section->setItemType(MenuItem::ITEM_TYPE_SECTION);
        $section->setParent($this->item(1));
        $violations = 0;
        $this->type()->validateSectionMustBeRoot($section, $this->context($violations));
        self::assertSame(1, $violations);

        $service = new MenuItem();
        $service->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        foreach ([null, ''] as $resolver) {
            $service->setLinkResolver($resolver);
            $this->type()->validateServiceRequiresResolver($service, $this->context($violations));
        }
        self::assertSame(3, $violations);

        $service->setLinkResolver('app.resolver');
        $this->type()->validateServiceRequiresResolver($service, $this->context($violations));
        $link = new MenuItem();
        $this->type()->validateServiceRequiresResolver($link, $this->context($violations));
        $this->type()->validateSectionMustBeRoot($link, $this->context($violations));
        self::assertSame(3, $violations, 'valid items add nothing');
    }

    public function testTheParentBreadcrumbMarksCyclesAndTooLongChains(): void
    {
        $breadcrumb = new ReflectionMethod(MenuItemConfigType::class, 'parentChoiceBreadcrumbLabel');

        $a = $this->item(1);
        $b = $this->item(2);
        $a->setParent($b);
        $b->setParent($a);
        self::assertStringContainsString('…', $breadcrumb->invoke(null, $a, 'en'), 'a cycle by id');

        $x = new MenuItem();
        $y = new MenuItem();
        $x->setParent($y);
        $y->setParent($x);
        self::assertStringContainsString('…', $breadcrumb->invoke(null, $x, 'en'), 'a cycle between unsaved items');

        $self = new MenuItem();
        $self->setParent($self);
        self::assertIsString($breadcrumb->invoke(null, $self, 'en'));

        $chain = new MenuItem();
        $top   = $chain;
        for ($i = 0; $i < 300; ++$i) {
            $parent = new MenuItem();
            $top->setParent($parent);
            $top = $parent;
        }
        self::assertStringStartsWith('…', $breadcrumb->invoke(null, $chain, 'en'), 'a chain longer than the guard is cut');
    }

    private function item(int $id): MenuItem
    {
        $item = new MenuItem();
        (new ReflectionProperty(MenuItem::class, 'id'))->setValue($item, $id);

        return $item;
    }

    private function type(): MenuItemConfigType
    {
        return new MenuItemConfigType($this->createMock(MenuItemRepository::class));
    }

    private function context(int &$violations): ExecutionContextInterface
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->willReturnSelf();
        $builder->method('setTranslationDomain')->willReturnSelf();
        $builder->method('addViolation')->willReturnCallback(static function () use (&$violations): void {
            ++$violations;
        });
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('buildViolation')->willReturn($builder);

        return $context;
    }
}
