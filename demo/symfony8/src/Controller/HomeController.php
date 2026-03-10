<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AppLocale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/{_locale}', requirements: ['_locale' => AppLocale::ROUTE_REQUIREMENT], defaults: ['_locale' => AppLocale::DEFAULT])]
class HomeController extends AbstractController
{
    public const APP_HOME_ROUTE = 'app_home';

    #[Route(path: '/', name: self::APP_HOME_ROUTE, methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
