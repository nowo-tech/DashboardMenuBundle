<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Controller\Api;

use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

use function is_array;

/**
 * JSON API for menu tree (SPA / React / Vue). Route: GET /api/menu/{code}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsController]
final readonly class MenuApiController
{
    public function __construct(
        private MenuTreeLoader $menuTreeLoader,
        private MenuUrlResolver $urlResolver,
        private MenuCodeResolverInterface $menuCodeResolver,
        private MenuLocaleResolver $localeResolver,
    ) {
    }

    public function __invoke(Request $request, string $code): JsonResponse
    {
        $resolvedCode  = $this->menuCodeResolver->resolveMenuCode($request, $code);
        $requestLocale = $request->query->getString('_locale', $request->getLocale());
        $locale        = $this->localeResolver->resolveLocale($requestLocale);
        $contextSets   = $this->parseContextSets($request);

        $tree = $this->menuTreeLoader->loadTree($resolvedCode, $locale, null, $contextSets);

        $data = $this->treeToArray($tree);

        return new JsonResponse($data);
    }

    /**
     * Parses _context_sets from query (JSON array of objects) or returns null for default [null, []].
     *
     * @return list<array<string, bool|int|string>|null>|null
     */
    private function parseContextSets(Request $request): ?array
    {
        $raw = $request->query->getString('_context_sets', '');
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $sets = [];
        foreach ($decoded as $item) {
            $sets[] = is_array($item) ? $item : null;
        }

        return $sets;
    }

    /**
     * @param list<array{item: \Nowo\DashboardMenuBundle\Entity\MenuItem, children: list<array>}> $tree
     *
     * @return list<array{label: string, href: string, routeName: string|null, icon: string|null, itemType: string, children: list<array<string, mixed>>}>
     */
    private function treeToArray(array $tree): array
    {
        $out = [];
        foreach ($tree as $node) {
            $item  = $node['item'];
            $out[] = [
                'label'     => $item->getLabel(),
                'href'      => $this->urlResolver->getHref($item),
                'routeName' => $item->getRouteName(),
                'icon'      => $item->getIcon(),
                'itemType'  => $item->getItemType(),
                'children'  => $this->treeToArray($node['children']),
            ];
        }

        return $out;
    }
}
