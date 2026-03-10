<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public const APP_HOME_ROUTE = 'app_home';
    public const APP_CONFIGURATION_ROUTE = 'app_configuration';
    public const APP_SECURITY_ROUTE = 'app_security';
    public const APP_ADMINISTRATION_ROUTE = 'app_administration';
    public const APP_PROFILE_ROUTE = 'app_profile';
    public const APP_SETTINGS_ROUTE = 'app_settings';
    public const APP_PAGE_INDEX_ROUTE = 'app_page_index';
    public const APP_PAGE_ROUTE = 'app_page';
    public const APP_INFO_INDEX_ROUTE = 'app_info_index';

    #[Route(path: '/', name: self::APP_HOME_ROUTE, methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    /** Ruta con slug y valor por defecto: /page → overview, /page/{page} → slug. */
    #[Route(path: '/page', name: self::APP_PAGE_INDEX_ROUTE, methods: ['GET'], defaults: ['page' => 'overview'])]
    #[Route(path: '/page/{page}', name: self::APP_PAGE_ROUTE, methods: ['GET'], requirements: ['page' => '[a-z0-9_-]+'], defaults: ['page' => 'overview'])]
    public function page(string $page = 'overview'): Response
    {
        $title = str_replace('-', ' ', ucwords($page, '-'));
        return $this->render('home/page.html.twig', ['page' => $page, 'title' => $title]);
    }
}
