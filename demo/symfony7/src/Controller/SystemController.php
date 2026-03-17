<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;
use function strlen;

use const PHP_URL_PATH;

/**
 * Routes without locale prefix: root, locale switch, favicon, well-known.
 */
class SystemController extends AbstractController
{
    public const APP_ROOT_ROUTE              = 'app_root';
    public const APP_SWITCH_LOCALE_ROUTE     = 'app_switch_locale';
    public const APP_FAVICON_ROUTE           = 'app_favicon';
    public const APP_WELL_KNOWN_CHROME_ROUTE = 'app_well_known_chrome';

    /** Redirects root / to home with locale (session or en). */
    #[Route(path: '/', name: self::APP_ROOT_ROUTE, methods: ['GET'])]
    public function root(Request $request): RedirectResponse
    {
        $locale = 'en';
        if ($request->hasSession()) {
            $s = $request->getSession()->get('_locale');
            if ($s !== null && in_array($s, ['en', 'es', 'fr'], true)) {
                $locale = $s;
            }
        }

        return $this->redirectToRoute('app_home', ['_locale' => $locale]);
    }

    /** Switches language by redirecting to the same path with the new locale in the URL. */
    #[Route(path: '/switch/{_locale}', name: self::APP_SWITCH_LOCALE_ROUTE, methods: ['GET'], requirements: ['_locale' => 'en|es|fr'])]
    public function switchLocale(Request $request, string $_locale): RedirectResponse
    {
        $referer = $request->headers->get('referer');
        $baseUrl = $request->getSchemeAndHttpHost() . $request->getBasePath();

        if ($referer !== null && str_starts_with($referer, $baseUrl)) {
            $path            = (string) parse_url($referer, PHP_URL_PATH);
            $basePath        = $request->getBasePath();
            $pathWithoutBase = $basePath !== '' && str_starts_with($path, $basePath)
                ? substr($path, strlen($basePath)) : $path;
            $pathWithoutBase = '/' . trim($pathWithoutBase, '/');
            if (preg_match('#^/(en|es|fr)(/|$)#', $pathWithoutBase, $m)) {
                $rest    = substr($pathWithoutBase, strlen($m[1]) + 1);
                $newPath = $basePath . '/' . $_locale . ($rest !== '' ? $rest : '');

                return $this->redirect($baseUrl . $newPath);
            }
        }

        return $this->redirectToRoute('app_home', ['_locale' => $_locale]);
    }

    #[Route(path: '/favicon.ico', name: self::APP_FAVICON_ROUTE, methods: ['GET'])]
    public function favicon(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/.well-known/appspecific/com.chrome.devtools.json', name: self::APP_WELL_KNOWN_CHROME_ROUTE, methods: ['GET'])]
    public function wellKnownChrome(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
