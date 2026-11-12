<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Worker;

use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use function array_map;

/**
 * Renders the `sidebar` tree for the user named in `X-User` plus the menu name read through the ORM repository (an
 * entity managed by the menu manager, as a dashboard page holds), or fails on purpose (worker tests).
 */
final readonly class WorkerMenuController
{
    public function __construct(
        private MenuExtension $menus,
        private MenuRepository $repository,
    ) {
    }

    public function menu(Request $request): JsonResponse
    {
        $tree = $this->menus->getMenuTree('sidebar', ['user' => (string) $request->headers->get('X-User')]);

        return new JsonResponse([
            'name'  => $this->repository->findOneByCodeAndContext('sidebar', null)?->getName(),
            'items' => array_map(static fn (array $node): string => $node['item']->getLabel(), $tree),
        ]);
    }

    public function boom(): never
    {
        throw new RuntimeException('controller failed');
    }
}
