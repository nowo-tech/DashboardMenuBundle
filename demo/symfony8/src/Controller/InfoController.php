<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AppLocale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/{_locale}', requirements: ['_locale' => AppLocale::ROUTE_REQUIREMENT], defaults: ['_locale' => AppLocale::DEFAULT])]
class InfoController extends AbstractController
{
    public const APP_INFO_INDEX_ROUTE = 'app_info_index';
    public const APP_INFO_ROUTE = 'app_info';

    /** Footer / info: cada slug es un posible valor de section (about, privacy, terms, contact, support, status). */
    public const INFO_SECTIONS = ['about', 'privacy', 'terms', 'contact', 'support', 'status'];

    #[Route(path: '/info', name: self::APP_INFO_INDEX_ROUTE, methods: ['GET'], defaults: ['section' => 'about'])]
    #[Route(path: '/info/{section}', name: self::APP_INFO_ROUTE, methods: ['GET'], requirements: ['section' => 'about|privacy|terms|contact|support|status'], defaults: ['section' => 'about'])]
    public function info(string $section = 'about'): Response
    {
        $title = ucfirst($section);

        return $this->render('home/info.html.twig', [
            'section' => $section,
            'title'   => $title,
        ]);
    }
}

