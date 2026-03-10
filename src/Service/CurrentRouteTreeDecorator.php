<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function array_key_exists;
use function in_array;
use function is_array;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * Decorates a menu tree with isCurrent and hasCurrentInBranch per node.
 * A link is current when the path matches and the current request contains
 * all the link's query params with the same values (the request may have extra params).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class CurrentRouteTreeDecorator
{
    public function __construct(
        private MenuUrlResolver $urlResolver,
    ) {
    }

    /**
     * Adds isCurrent (link matches current path + query subset) and hasCurrentInBranch to each node.
     *
     * @param list<array{item: MenuItem, children: list<array>}> $tree
     *
     * @return list<array{item: MenuItem, children: list<array>, isCurrent: bool, hasCurrentInBranch: bool}>
     */
    public function decorate(array $tree, Request $request): array
    {
        $normalizedCurrentPath = $this->normalizePath($request->getPathInfo());
        $currentQuery          = $request->query->all();

        return array_map(
            /**
             * @param array{item: MenuItem, children: list<array>} $node
             */
            fn (array $node): array => $this->decorateNode($node, $normalizedCurrentPath, $currentQuery),
            $tree,
        );
    }

    /**
     * @param array{item: MenuItem, children: list<array>} $node
     * @param array<string, mixed> $currentQuery
     *
     * @return array{item: MenuItem, children: list<array>, isCurrent: bool, hasCurrentInBranch: bool}
     */
    private function decorateNode(array $node, string $normalizedCurrentPath, array $currentQuery): array
    {
        $item     = $node['item'];
        $children = $node['children'];
        $children = array_map(
            fn (array $child): array => $this->decorateNode($child, $normalizedCurrentPath, $currentQuery),
            $children,
        );

        $isCurrent          = $this->isLinkCurrent($item, $normalizedCurrentPath, $currentQuery);
        $hasCurrentInBranch = $isCurrent || $this->anyChildHasCurrentInBranch($children);

        return [
            'item'               => $item,
            'children'           => $children,
            'isCurrent'          => $isCurrent,
            'hasCurrentInBranch' => $hasCurrentInBranch,
        ];
    }

    /**
     * True when path matches and every query param of the link is present in the current request with the same value.
     *
     * @param array<string, mixed> $currentQuery
     */
    private function isLinkCurrent(MenuItem $item, string $normalizedCurrentPath, array $currentQuery): bool
    {
        if ($item->getItemType() !== MenuItem::ITEM_TYPE_LINK) {
            return false;
        }
        $href     = $this->urlResolver->getHref($item, UrlGeneratorInterface::ABSOLUTE_PATH);
        $linkPath = $this->normalizePath($href);
        if ($linkPath === '' || $linkPath === '#') {
            return false;
        }
        if ($linkPath !== $normalizedCurrentPath) {
            return false;
        }

        $linkQuery = $this->parseQueryFromUrl($href);
        foreach ($linkQuery as $key => $linkValue) {
            if (!array_key_exists($key, $currentQuery)) {
                return false;
            }
            $currentValue = $currentQuery[$key];
            if (is_array($linkValue) || is_array($currentValue)) {
                if (!is_array($linkValue) || !is_array($currentValue) || $linkValue != $currentValue) {
                    return false;
                }
            } elseif ((string) $currentValue !== (string) $linkValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int|string, array<mixed>|string>
     */
    private function parseQueryFromUrl(string $url): array
    {
        $queryString = parse_url($url, PHP_URL_QUERY);
        if (in_array($queryString, [null, false, ''], true)) {
            return [];
        }
        $params = [];
        parse_str($queryString, $params);

        return $params;
    }

    /**
     * @param list<array{hasCurrentInBranch: bool}> $children
     */
    private function anyChildHasCurrentInBranch(array $children): bool
    {
        foreach ($children as $child) {
            if ($child['hasCurrentInBranch']) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '#') {
            return $path;
        }
        $path = parse_url($path, PHP_URL_PATH);
        if ($path === null || $path === false) {
            return '';
        }

        return rtrim($path, '/') ?: '/';
    }
}
