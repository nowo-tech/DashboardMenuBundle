<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
    ) {
    }

    public function getHref(MenuItem $item, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        if ($item->getLinkType() === MenuItem::LINK_TYPE_EXTERNAL && $item->getExternalUrl() !== null) {
            return $item->getExternalUrl();
        }

        $routeName = $item->getRouteName();
        if ($routeName === null || $routeName === '') {
            return '#';
        }

        $params  = $item->getRouteParams() ?? [];
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof \Symfony\Component\HttpFoundation\Request && !array_key_exists('_locale', $params)) {
            $params = ['_locale' => $request->getLocale()] + $params;
        }

        return $this->urlGenerator->generate($routeName, $params, $referenceType);
    }
}
