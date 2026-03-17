<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AppLocale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/{_locale}', requirements: ['_locale' => AppLocale::ROUTE_REQUIREMENT], defaults: ['_locale' => AppLocale::DEFAULT])]
class PageController extends AbstractController
{
    public const APP_PAGE_INDEX_ROUTE = 'app_page_index';
    public const APP_PAGE_ROUTE       = 'app_page';

    /** Route with slug and default value: /page → overview, /page/{page} → slug. Compatible with routeParams ['page' => '...']. */
    #[Route(path: '/page', name: self::APP_PAGE_INDEX_ROUTE, methods: ['GET'], defaults: ['page' => 'overview'])]
    #[Route(path: '/page/{page}', name: self::APP_PAGE_ROUTE, methods: ['GET'], requirements: ['page' => '[a-z0-9_-]+'], defaults: ['page' => 'overview'])]
    public function page(string $page = 'overview'): Response
    {
        $title = str_replace('-', ' ', ucwords($page, '-'));

        return $this->render('home/page.html.twig', [
            'page'  => $page,
            'title' => $title,
        ]);
    }
}
