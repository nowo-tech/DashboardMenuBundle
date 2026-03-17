<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AppLocale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/{_locale}', requirements: ['_locale' => AppLocale::ROUTE_REQUIREMENT], defaults: ['_locale' => AppLocale::DEFAULT])]
class SecurityController extends AbstractController
{
    public const APP_SECURITY_ROUTE = 'app_security';

    /** Slug con valor por defecto: /security y /security/{section}. */
    #[Route(path: '/security', name: self::APP_SECURITY_ROUTE, methods: ['GET'], defaults: ['section' => 'overview'])]
    #[Route(path: '/security/{section}', name: 'app_security_section', methods: ['GET'], requirements: ['section' => '[a-z0-9_-]+'], defaults: ['section' => 'overview'])]
    public function security(string $section = 'overview'): Response
    {
        return $this->render('home/page.html.twig', [
            'page'    => 'security',
            'title'   => 'Security',
            'section' => $section,
        ]);
    }
}
