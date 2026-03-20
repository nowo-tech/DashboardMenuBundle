<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Exception;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

use function array_key_exists;

/**
 * Resolves the href for a menu item (route or external URL).
 * When the app uses locale in routes, injects the current request locale so links keep the same language.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuUrlResolver
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private RouterInterface $router,
    ) {
    }

    public function getHref(MenuItem $item, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
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

        $params  = $item->getRouteParams() ?? [];
        $request = $this->requestStack->getCurrentRequest();
        $routeNeedsLocale = false;

        // Complete missing path variables from current route params so links can reuse e.g. id/locale from the current URL
        try {
            $route = $this->router->getRouteCollection()->get($routeName);
            if ($route instanceof \Symfony\Component\Routing\Route && $request instanceof Request) {
                $compiled      = $route->compile();
                $pathVars      = $compiled->getPathVariables();
                $routeNeedsLocale = in_array('_locale', $pathVars, true);
                $currentParams = (array) $request->attributes->get('_route_params', []);
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
