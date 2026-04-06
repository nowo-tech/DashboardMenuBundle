<?php

declare(strict_types=1);

namespace App\Service;

use App\Controller\HomeController;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuLinkResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Demo {@see MenuLinkResolverInterface} for the slim Symfony 7 demo: links to {@see HomeController::page}
 * using {@see MenuItem::getRouteParams()} key <code>page</code> (slug; default <code>overview</code>).
 */
final class DemoMenuLinkResolver implements MenuLinkResolverInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
    {
        $params = $item->getRouteParams() ?? [];
        $page   = $params['page'] ?? 'overview';
        if (!is_string($page) || !preg_match('/^[a-z0-9_-]+$/', $page)) {
            $page = 'overview';
        }

        return $this->urlGenerator->generate(HomeController::APP_PAGE_ROUTE, ['page' => $page]);
    }
}
