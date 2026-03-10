<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\AppLocale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/{_locale}', requirements: ['_locale' => AppLocale::ROUTE_REQUIREMENT], defaults: ['_locale' => AppLocale::DEFAULT])]
class ProfileController extends AbstractController
{
    public const APP_PROFILE_ROUTE = 'app_profile';

    #[Route(path: '/profile', name: self::APP_PROFILE_ROUTE, methods: ['GET'])]
    public function profile(): Response
    {
        return $this->render('home/page.html.twig', [
            'page' => 'profile',
            'title' => 'Profile',
        ]);
    }
}

