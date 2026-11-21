<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuItemBadgeResolver;
use Nowo\DashboardMenuBundle\Tests\Support\RecordingBadgeProvider;
use Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Item badges (2.27.0): lazy, once per item and request, never breaks the menu.
 */
final class MenuItemBadgeResolverTest extends TestCase
{
    public function testProviderIsCalledOncePerItemAndRequest(): void
    {
        $provider = $this->provider(5);
        $stack    = $this->stack();
        $resolver = $this->resolver(['customers_questions' => $provider], $stack);

        self::assertSame(5, $resolver->count($this->item('customers_questions')));
        self::assertSame(5, $resolver->count($this->item('customers_questions')));
        self::assertSame(1, $provider->calls);
        self::assertSame(['customers_questions'], $provider->keys);
    }

    public function testMemoDoesNotSurviveTheRequestEvenWithoutReset(): void
    {
        $provider = $this->provider(2);
        $stack    = $this->stack();
        $resolver = $this->resolver(['q' => $provider], $stack);

        self::assertSame(2, $resolver->count($this->item('q')));

        // Worker mode, kernel not reset: the next request is a different Request object.
        $stack->pop();
        $stack->push(Request::create('/next'));
        $provider->default = 7;

        self::assertSame(7, $resolver->count($this->item('q')));
        self::assertSame(2, $provider->calls);
    }

    public function testResetClearsTheMemo(): void
    {
        $provider = $this->provider(2);
        $resolver = $this->resolver(['q' => $provider], $this->stack());

        self::assertSame(2, $resolver->count($this->item('q')));
        $provider->default = 3;
        self::assertSame(2, $resolver->count($this->item('q')));

        $resolver->reset();

        self::assertSame(3, $resolver->count($this->item('q')));
    }

    public function testOutsideARequestTheMemoLivesUntilReset(): void
    {
        $provider = $this->provider(4);
        $resolver = $this->resolver(['q' => $provider], new RequestStack());

        self::assertSame(4, $resolver->count($this->item('q')));
        self::assertSame(4, $resolver->count($this->item('q')));
        self::assertSame(1, $provider->calls);

        $resolver->reset();
        self::assertSame(4, $resolver->count($this->item('q')));
        self::assertSame(2, $provider->calls);
    }

    public function testNullAndZeroAndNegativeHideTheBadgeAndAreMemoised(): void
    {
        foreach ([null, 0, -3] as $value) {
            $provider = $this->provider($value);
            $resolver = $this->resolver(['q' => $provider], $this->stack());

            self::assertSame(0, $resolver->count($this->item('q')));
            self::assertSame(0, $resolver->count($this->item('q')));
            self::assertSame(1, $provider->calls);
        }
    }

    public function testExceptionHidesTheBadgeIsLoggedAndNotRetriedInTheRequest(): void
    {
        $provider          = $this->provider(1);
        $provider->default = new RuntimeException('db down');
        $logger            = new RecordingLogger();
        $resolver          = $this->resolver(['q' => $provider], $this->stack(), $logger);

        self::assertSame(0, $resolver->count($this->item('q')));
        self::assertSame(0, $resolver->count($this->item('q')));
        self::assertSame(1, $provider->calls);
        self::assertCount(1, $logger->records);
        self::assertSame('q', $logger->records[0][2]['item']);
        self::assertInstanceOf(RuntimeException::class, $logger->records[0][2]['exception']);
    }

    public function testExceptionWithoutLoggerIsSwallowed(): void
    {
        $provider          = $this->provider(1);
        $provider->default = new RuntimeException('x');

        self::assertSame(0, $this->resolver(['q' => $provider], $this->stack())->count($this->item('q')));
    }

    public function testServiceThatIsNotAProviderHidesTheBadge(): void
    {
        $locator  = new ServiceLocator(['q' => static fn (): object => new stdClass()]);
        $resolver = new MenuItemBadgeResolver($locator, $this->stack());

        self::assertSame(0, $resolver->count($this->item('q')));
    }

    public function testProviderIsNotCalledWithoutAKeyOrForOtherItems(): void
    {
        $provider = $this->provider(9);
        $resolver = $this->resolver(['q' => $provider], $this->stack());

        self::assertSame(0, $resolver->count($this->item(null)));
        self::assertSame(0, $resolver->count($this->item('')));
        self::assertSame(0, $resolver->count($this->item('other')));
        self::assertSame(0, $resolver->count('q'));
        self::assertSame(0, $resolver->count(null));
        self::assertSame(0, $provider->calls);
    }

    public function testNoProvidersMeansNothingIsResolved(): void
    {
        self::assertSame(0, $this->resolver([], $this->stack())->count($this->item('q')));
    }

    /**
     * @param array<string, object> $providers
     */
    private function resolver(array $providers, RequestStack $stack, ?LoggerInterface $logger = null): MenuItemBadgeResolver
    {
        $factories = [];
        foreach ($providers as $key => $provider) {
            $factories[$key] = static fn (): object => $provider;
        }

        return new MenuItemBadgeResolver(new ServiceLocator($factories), $stack, $logger);
    }

    private function stack(): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        return $stack;
    }

    private function item(?string $route): MenuItem
    {
        $item = new MenuItem();
        $item->setRouteName($route);

        return $item;
    }

    private function provider(?int $value): RecordingBadgeProvider
    {
        return new RecordingBadgeProvider([], $value);
    }
}
