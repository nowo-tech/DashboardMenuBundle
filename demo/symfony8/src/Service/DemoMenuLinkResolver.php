<?php

declare(strict_types=1);

namespace App\Service;

use App\Controller\InfoController;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuLinkResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Demo {@see MenuLinkResolverInterface}: builds a link to the Info pages from {@see MenuItem::getRouteParams()}
 * (expects key <code>section</code> among the allowed path segments; default <code>about</code>).
 */
final class DemoMenuLinkResolver implements MenuLinkResolverInterface
{
    /** @var list<string> */
    private const ALLOWED_SECTIONS = ['about', 'privacy', 'terms', 'contact', 'support', 'status'];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        $params  = $item->getRouteParams() ?? [];
        $section = $params['section'] ?? 'about';
        if (!is_string($section) || !in_array($section, self::ALLOWED_SECTIONS, true)) {
            $section = 'about';
        }

        return $this->urlGenerator->generate(InfoController::APP_INFO_ROUTE, ['section' => $section]);
    }
}
