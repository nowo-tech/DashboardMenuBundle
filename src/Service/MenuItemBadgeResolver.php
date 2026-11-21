<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use LogicException;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use WeakMap;

use function is_int;
use function sprintf;

/**
 * The badge count of a sidebar menu item (2.27), from the {@see MenuBadgeProviderInterface} registered for the item's
 * key (its route name). Opt-in: with no provider registered nothing is resolved and the menu markup is unchanged.
 *
 * Lazy and memoised per request: a provider runs at most once per item key and request, however many times the item
 * renders (sidebar and footer). The memo hangs on the main Request object (a WeakMap), so it can never outlive the
 * request even when the kernel is not reset between requests (FrankenPHP worker mode); {@see reset()} also clears it
 * (and the memo used outside a request, e.g. console). No static state.
 *
 * Failures never reach the page: a provider that throws, or returns something other than a number, is logged and its
 * badge is hidden (the failure is memoised too, so a broken provider is not called again in the same request).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuItemBadgeResolver implements ResetInterface
{
    /** @var WeakMap<Request, array<string, int>> */
    private WeakMap $perRequest;

    /** @var array<string, int> memo outside a request */
    private array $outsideRequest = [];

    public function __construct(
        private readonly ContainerInterface $providers,
        private readonly RequestStack $requestStack,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->perRequest = new WeakMap();
    }

    /**
     * The count to show, 0 = no badge. Never throws.
     */
    public function count(mixed $item): int
    {
        if (!$item instanceof MenuItem) {
            return 0;
        }
        $key = $item->getRouteName();
        if ($key === null || $key === '' || !$this->providers->has($key)) {
            return 0;
        }

        $request = $this->requestStack->getMainRequest();
        $memo    = $request instanceof Request ? ($this->perRequest[$request] ?? []) : $this->outsideRequest;
        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $count = $this->resolve($key);

        $memo[$key] = $count;
        if ($request instanceof Request) {
            $this->perRequest[$request] = $memo;
        } else {
            $this->outsideRequest = $memo;
        }

        return $count;
    }

    public function reset(): void
    {
        $this->perRequest     = new WeakMap();
        $this->outsideRequest = [];
    }

    private function resolve(string $key): int
    {
        try {
            $provider = $this->providers->get($key);
            if (!$provider instanceof MenuBadgeProviderInterface) {
                throw new LogicException(sprintf('The menu badge provider for "%s" must implement %s.', $key, MenuBadgeProviderInterface::class));
            }
            $count = $provider->badge($key);
        } catch (Throwable $e) {
            $this->logger?->error('Menu badge provider failed; the badge is hidden.', ['item' => $key, 'exception' => $e]);

            return 0;
        }

        return is_int($count) && $count > 0 ? $count : 0;
    }
}
