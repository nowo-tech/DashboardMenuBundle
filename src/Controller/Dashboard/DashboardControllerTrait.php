<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Controller\Dashboard;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Shared helpers for all dashboard controllers.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
trait DashboardControllerTrait
{
    /**
     * Redirect to the request referer when it is a safe same-origin URL; otherwise to the given route.
     *
     * @param array<string, mixed> $routeParams
     */
    private function redirectToRefererOr(Request $request, string $route, array $routeParams = [], ?string $fragment = null): RedirectResponse
    {
        $referer = $request->headers->get('Referer');
        if ($referer !== null && $referer !== '') {
            $parsed = parse_url($referer);
            $host   = $parsed['host'] ?? '';
            if ($host !== '' && $host === $request->getHost()) {
                $base = str_contains($referer, '#') ? explode('#', $referer, 2)[0] : $referer;
                $url  = $fragment !== null && $fragment !== '' ? $base . '#' . $fragment : $base;

                return new RedirectResponse($url);
            }
        }

        $params = $routeParams;
        if ($fragment !== null && $fragment !== '') {
            $params['_fragment'] = $fragment;
        }

        return $this->redirectToRoute($route, $params);
    }

    /**
     * Returns Bootstrap modal CSS class for each modal type (e.g. '' for normal, 'modal-lg', 'modal-xl').
     *
     * @param array<string, string> $sizes
     *
     * @return array{menu_form: string, copy: string, item_form: string, delete: string}
     */
    private static function resolveModalClasses(array $sizes): array
    {
        $map = static fn (string $v): string => match ($v) {
            'lg'    => 'modal-lg',
            'xl'    => 'modal-xl',
            default => '',
        };

        return [
            'menu_form' => $map($sizes['menu_form'] ?? 'normal'),
            'copy'      => $map($sizes['copy'] ?? 'normal'),
            'item_form' => $map($sizes['item_form'] ?? 'lg'),
            'delete'    => $map($sizes['delete'] ?? 'normal'),
        ];
    }
}
