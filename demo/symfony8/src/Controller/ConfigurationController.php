<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AppLocale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/{_locale}', requirements: ['_locale' => AppLocale::ROUTE_REQUIREMENT], defaults: ['_locale' => AppLocale::DEFAULT])]
class ConfigurationController extends AbstractController
{
    public const APP_CONFIGURATION_ROUTE  = 'app_configuration';
    public const APP_SETTINGS_ROUTE       = 'app_settings';
    public const APP_ADMINISTRATION_ROUTE = 'app_administration';

    /** Slug con valor por defecto: /configuration y /configuration/{section}. */
    #[Route(path: '/configuration', name: self::APP_CONFIGURATION_ROUTE, methods: ['GET'], defaults: ['section' => 'general'])]
    #[Route(path: '/configuration/{section}', name: 'app_configuration_section', methods: ['GET'], requirements: ['section' => '[a-z0-9_-]+'], defaults: ['section' => 'general'])]
    public function configuration(string $section = 'general'): Response
    {
        return $this->render('home/page.html.twig', [
            'page'    => 'configuration',
            'title'   => 'Configuration',
            'section' => $section,
        ]);
    }

    #[Route(path: '/settings', name: self::APP_SETTINGS_ROUTE, methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('home/page.html.twig', [
            'page'  => 'settings',
            'title' => 'Settings',
        ]);
    }

    #[Route(path: '/administration', name: self::APP_ADMINISTRATION_ROUTE, methods: ['GET'])]
    public function administration(): Response
    {
        return $this->render('home/page.html.twig', [
            'page'  => 'administration',
            'title' => 'Administration',
        ]);
    }
}
