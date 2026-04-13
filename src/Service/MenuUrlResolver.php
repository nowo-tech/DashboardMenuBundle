<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Exception;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;

/**
 * Resolves the href for a menu item (route, external URL, or itemType "service" via MenuLinkResolverInterface).
 * When the app uses locale in routes, injects the current request locale so links keep the same language.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuUrlResolver
{
    /**
     * @param array<string, string> $menuLinkResolverChoices resolved id => label (after compiler pass)
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private RouterInterface $router,
        private ContainerInterface $container,
        private array $menuLinkResolverChoices = [],
    ) {
    }

    public function getHref(MenuItem $item, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $runtime = $item->getRuntimeHref();
        if ($runtime !== null && $runtime !== '') {
            return $this->normalizeHrefReferenceType(trim($runtime), $referenceType);
        }

        if ($item->getItemType() === MenuItem::ITEM_TYPE_SERVICE) {
            return $this->getHrefFromServiceResolver($item, $referenceType);
        }

        $linkType = $item->getLinkType();
        if ($linkType === null) {
            return '#';
        }
        if ($linkType === MenuItem::LINK_TYPE_EXTERNAL && $item->getExternalUrl() !== null) {
            return $item->getExternalUrl();
        }

        $routeName = $item->getRouteName();
        if ($routeName === null || $routeName === '') {
            return '#';
        }

        $params           = $item->getRouteParams() ?? [];
        $request          = $this->requestStack->getCurrentRequest();
        $routeNeedsLocale = false;

        // Complete missing path variables from current route params so links can reuse e.g. id/locale from the current URL
        try {
            $route = $this->router->getRouteCollection()->get($routeName);
            if ($route instanceof \Symfony\Component\Routing\Route && $request instanceof Request) {
                $compiled         = $route->compile();
                $pathVars         = $compiled->getPathVariables();
                $routeNeedsLocale = in_array('_locale', $pathVars, true);
                $currentParams    = (array) $request->attributes->get('_route_params', []);
                foreach ($pathVars as $var) {
                    if (!array_key_exists($var, $params) && array_key_exists($var, $currentParams)) {
                        $params[$var] = $currentParams[$var];
                    }
                }
            }
        } catch (Exception $e) {
            $this->addFlashException($request, $e);
        }

        // Only inject `_locale` when the target route needs it as a path variable.
        // Otherwise it would become a query parameter (e.g. `? _locale=...`) on routes that don't declare it.
        if ($request instanceof Request && $routeNeedsLocale && !array_key_exists('_locale', $params)) {
            $params = ['_locale' => $request->getLocale()] + $params;
        }

        try {
            return $this->urlGenerator->generate($routeName, $params, $referenceType);
        } catch (Exception $e) {
            $this->addFlashException($request, $e);

            return '#';
        }
    }

    private function getHrefFromServiceResolver(MenuItem $item, int $referenceType): string
    {
        $rawId = $item->getLinkResolver();
        if ($rawId === null || $rawId === '') {
            return '#';
        }

        $serviceId = $this->normalizeMenuLinkResolverServiceId($rawId);
        if ($serviceId === null || !$this->container->has($serviceId)) {
            return '#';
        }

        try {
            $resolver = $this->container->get($serviceId);
        } catch (Exception) {
            return '#';
        }

        if (!$resolver instanceof MenuLinkResolverInterface) {
            return '#';
        }

        $request = $this->requestStack->getCurrentRequest();
        $ctx     = $request;
        try {
            $resolved = $resolver->resolveHref($item, $request, $ctx);
        } catch (Exception $e) {
            $this->addFlashException($request, $e);

            return '#';
        }

        if (is_array($resolved)) {
            return '#';
        }

        if (!is_string($resolved)) {
            return '#';
        }

        $href = trim($resolved);
        if ($href === '' || $href === '#') {
            return '#';
        }

        return $this->normalizeHrefReferenceType($href, $referenceType);
    }

    private function normalizeHrefReferenceType(string $href, int $referenceType): string
    {
        if ($referenceType === UrlGeneratorInterface::ABSOLUTE_URL && str_starts_with($href, '/')) {
            $request = $this->requestStack->getCurrentRequest();
            if ($request instanceof Request) {
                return $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $href;
            }
        }

        return $href;
    }

    private function normalizeMenuLinkResolverServiceId(string $serviceId): string
    {
        if ($this->container->has($serviceId)) {
            return $serviceId;
        }

        foreach ($this->menuLinkResolverChoices as $id => $label) {
            if ($label === $serviceId && $this->container->has($id)) {
                return $id;
            }
        }

        return $serviceId;
    }

    private function addFlashException(?Request $request, Exception $e): void
    {
        if (!$request instanceof Request || !$request->hasSession()) {
            return;
        }
        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session  = $request->getSession();
        $flashBag = $session->getFlashBag();
        $flashBag->add('error', 'Menu URL: ' . $e->getMessage());
    }
}
