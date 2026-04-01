<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Controller\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JsonException;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\CopyMenuType;
use Nowo\DashboardMenuBundle\Form\ImportMenuType;
use Nowo\DashboardMenuBundle\Form\MenuItemType;
use Nowo\DashboardMenuBundle\Form\MenuType;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\ImportExportRateLimiter;
use Nowo\DashboardMenuBundle\Service\MenuExporter;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function array_is_list;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_encode;
use function strlen;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;
use const SORT_NATURAL;

/**
 * Dashboard to manage menus and menu items.
 * Enable in config: nowo_dashboard_menu.dashboard.enabled: true
 * Import routes with prefix (e.g. /admin/menus): @NowoDashboardMenuBundle/Resources/config/routes_dashboard.yaml.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsController]
#[Route(path: '', name: 'nowo_dashboard_menu_dashboard_')]
final class MenuDashboardController extends AbstractController
{
    public const ROUTE_INDEX                     = 'nowo_dashboard_menu_dashboard_index';
    public const ROUTE_SHOW                      = 'nowo_dashboard_menu_dashboard_show';
    public const ROUTE_SHOW_ITEMS_REORDER        = 'nowo_dashboard_menu_dashboard_show_items_reorder';
    public const ROUTE_MENU_NEW                  = 'nowo_dashboard_menu_dashboard_menu_new';
    public const ROUTE_MENU_EDIT                 = 'nowo_dashboard_menu_dashboard_menu_edit';
    public const ROUTE_MENU_DELETE               = 'nowo_dashboard_menu_dashboard_menu_delete';
    public const ROUTE_MENU_COPY                 = 'nowo_dashboard_menu_dashboard_menu_copy';
    public const ROUTE_ITEM_NEW                  = 'nowo_dashboard_menu_dashboard_item_new';
    public const ROUTE_ITEM_EDIT                 = 'nowo_dashboard_menu_dashboard_item_edit';
    public const ROUTE_ITEM_DELETE               = 'nowo_dashboard_menu_dashboard_item_delete';
    public const ROUTE_ITEM_MOVE_UP              = 'nowo_dashboard_menu_dashboard_item_move_up';
    public const ROUTE_ITEM_MOVE_DOWN            = 'nowo_dashboard_menu_dashboard_item_move_down';
    public const ROUTE_ITEMS_REINDEX_POSITIONS   = 'nowo_dashboard_menu_dashboard_items_reindex_positions';
    public const ROUTE_ITEMS_CHECK_PARENT_CYCLES = 'nowo_dashboard_menu_dashboard_items_check_parent_cycles';
    public const ROUTE_ITEMS_REORDER_TREE        = 'nowo_dashboard_menu_dashboard_items_reorder_tree';
    public const ROUTE_EXPORT_MENU               = 'nowo_dashboard_menu_dashboard_export_menu';
    public const ROUTE_EXPORT_ALL                = 'nowo_dashboard_menu_dashboard_export_all';
    public const ROUTE_IMPORT                    = 'nowo_dashboard_menu_dashboard_import';

    /**
     * @return array<string, string>
     */
    private function getDashboardRoutes(): array
    {
        return [
            'index'                     => self::ROUTE_INDEX,
            'show'                      => self::ROUTE_SHOW,
            'show_items_reorder'        => self::ROUTE_SHOW_ITEMS_REORDER,
            'menu_new'                  => self::ROUTE_MENU_NEW,
            'menu_edit'                 => self::ROUTE_MENU_EDIT,
            'menu_delete'               => self::ROUTE_MENU_DELETE,
            'menu_copy'                 => self::ROUTE_MENU_COPY,
            'item_new'                  => self::ROUTE_ITEM_NEW,
            'item_edit'                 => self::ROUTE_ITEM_EDIT,
            'item_delete'               => self::ROUTE_ITEM_DELETE,
            'item_move_up'              => self::ROUTE_ITEM_MOVE_UP,
            'item_move_down'            => self::ROUTE_ITEM_MOVE_DOWN,
            'items_reindex_positions'   => self::ROUTE_ITEMS_REINDEX_POSITIONS,
            'items_check_parent_cycles' => self::ROUTE_ITEMS_CHECK_PARENT_CYCLES,
            'items_reorder_tree'        => self::ROUTE_ITEMS_REORDER_TREE,
            'export_menu'               => self::ROUTE_EXPORT_MENU,
            'export_all'                => self::ROUTE_EXPORT_ALL,
            'import'                    => self::ROUTE_IMPORT,
        ];
    }

    /**
     * @param list<string> $routeNameExcludePatterns Regex patterns to exclude route names from the selector
     * @param list<string> $locales Enabled locales for item labels configured in the bundle
     * @param array<string, string> $modalSizes Modal size per type: menu_form, copy, item_form, delete (values: normal, lg, xl)
     * @param string|null $iconSelectorScriptUrl Optional URL of the icon-selector script (Stimulus/UX) for the item form modal
     * @param string|null $stimulusScriptUrl Optional URL of the script that loads Stimulus + Live controller (sets window.Stimulus). When null and Live is enabled, the bundle uses its own asset.
     * @param int $importMaxBytes Maximum allowed size in bytes for JSON import uploads (default 2 MiB)
     * @param int $positionStep Gap for dashboard "re-index positions" action (e.g. 100 → 100, 200, 300 per sibling group)
     * @param ImportExportRateLimiter $importExportRateLimiter Rate limiter for import/export (no-op when disabled in config)
     * @param bool $itemFormLiveComponentEnabled Whether the item form modal uses the Live Component (true when symfony/ux-live-component is installed)
     */
    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly MenuItemRepository $menuItemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly MenuExporter $menuExporter,
        private readonly MenuImporter $menuImporter,
        private readonly array $routeNameExcludePatterns,
        private readonly array $locales,
        private readonly bool $paginationEnabled,
        private readonly int $paginationPerPage,
        private readonly array $modalSizes,
        private readonly ?string $iconSelectorScriptUrl,
        private readonly ?string $stimulusScriptUrl,
        private readonly int $importMaxBytes,
        private readonly int $positionStep,
        private readonly ImportExportRateLimiter $importExportRateLimiter,
        private readonly bool $itemFormLiveComponentEnabled = false,
    ) {
    }

    /**
     * Returns Bootstrap modal CSS class for each modal type (e.g. '' for normal, 'modal-lg', 'modal-xl').
     *
     * @return array{menu_form: string, copy: string, item_form: string, delete: string}
     */
    private function getModalClasses(): array
    {
        $map = static fn (string $v): string => match ($v) {
            'lg'    => 'modal-lg',
            'xl'    => 'modal-xl',
            default => '',
        };
        $sizes = $this->modalSizes;

        return [
            'menu_form' => $map($sizes['menu_form'] ?? 'normal'),
            'copy'      => $map($sizes['copy'] ?? 'normal'),
            'item_form' => $map($sizes['item_form'] ?? 'lg'),
            'delete'    => $map($sizes['delete'] ?? 'normal'),
        ];
    }

    #[Route(path: '', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $page   = max(1, (int) $request->query->get('page', 1));

        if ($this->paginationEnabled) {
            $perPage    = $this->paginationPerPage;
            $total      = $this->menuRepository->countForDashboard($search);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
            $page       = max(1, min($page, $totalPages > 0 ? $totalPages : 1));
            $offset     = ($page - 1) * $perPage;
            $menus      = $this->menuRepository->findForDashboard($search, $offset, $perPage);
            $pagination = [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'total_pages'  => $totalPages,
            ];
        } else {
            $menus      = $this->menuRepository->findForDashboard($search, 0);
            $pagination = null;
        }

        $menuIds = [];
        foreach ($menus as $menu) {
            if ($menu->getId() !== null) {
                $menuIds[] = (int) $menu->getId();
            }
        }
        $menuItemCounts = $this->menuItemRepository->countForMenus($menuIds);

        $newMenu     = new Menu();
        $newMenuForm = $this->createForm(MenuType::class, $newMenu, [
            'action'  => $this->generateUrl(self::ROUTE_MENU_NEW),
            'section' => 'basic',
        ]);

        return $this->render('@NowoDashboardMenuBundle/dashboard/index.html.twig', [
            'menus'                    => $menus,
            'menu_item_counts'         => $menuItemCounts,
            'search'                   => $search,
            'pagination'               => $pagination,
            'new_menu_form'            => $newMenuForm,
            'dashboard_show_route'     => self::ROUTE_SHOW,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'modal_classes'            => $this->getModalClasses(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
            'stimulus_script_url'      => $this->stimulusScriptUrl,
        ]);
    }

    #[Route(path: '/menu/new', name: 'menu_new', methods: ['GET', 'POST'])]
    public function newMenu(Request $request): Response
    {
        $menu = new Menu();
        $form = $this->createForm(MenuType::class, $menu, [
            'action'  => $this->generateUrl(self::ROUTE_MENU_NEW),
            'section' => 'basic',
        ]);
        $form->handleRequest($request);
        $menu = $form->getData() ?? $menu;
        if ($form->isSubmitted() && $form->isValid()) {
            if ($menu->getCode() !== '') {
                $existing = $this->menuRepository->findOneByCodeAndContext($menu->getCode(), $menu->getContext());
                if ($existing instanceof Menu) {
                    $form->addError(new FormError($this->translator->trans('dashboard.unique_code_context_error', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN)));
                } else {
                    $this->entityManager->persist($menu);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'Menu created.');

                    return $this->redirectToRefererOr($request, self::ROUTE_INDEX, []);
                }
            } else {
                $this->addFlash('error', 'Code is required.');
            }
        }

        if ($request->query->get('_partial')) {
            return $this->render('@NowoDashboardMenuBundle/dashboard/_menu_form_partial.html.twig', [
                'form'             => $form,
                'is_edit'          => false,
                'dashboard_routes' => $this->getDashboardRoutes(),
            ]);
        }

        return $this->render('@NowoDashboardMenuBundle/dashboard/menu_form.html.twig', [
            'form'                     => $form,
            'is_edit'                  => false,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
            'stimulus_script_url'      => $this->stimulusScriptUrl,
        ]);
    }

    #[Route(path: '/{id}/items/reorder', name: 'show_items_reorder', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showItemsReorder(int $id): Response
    {
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $itemsRaw  = $this->menuItemRepository->findAllForMenuOrderedByTree($menu, 'en');
        $itemsTree = $this->buildMenuItemTreeForSortable($itemsRaw);

        return $this->render('@NowoDashboardMenuBundle/dashboard/show_items_reorder.html.twig', [
            'menu'                     => $menu,
            'items_tree'               => $itemsTree,
            'modal_classes'            => $this->getModalClasses(),
            'dashboard_show_route'     => self::ROUTE_SHOW,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
            'stimulus_script_url'      => $this->stimulusScriptUrl,
        ]);
    }

    #[Route(path: '/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $items            = $this->menuItemRepository->findAllForMenuOrderedByTree($menu, 'en');
        $items            = $this->orderItemsByTreeForDisplay($items);
        $siblings         = $this->computeSiblingMaps($items);
        $itemDepths       = $this->computeItemDepths($items);
        $itemParentLabels = $this->computeParentLabels($items, 'en');

        return $this->render('@NowoDashboardMenuBundle/dashboard/show.html.twig', [
            'menu'                     => $menu,
            'items'                    => $items,
            'modal_classes'            => $this->getModalClasses(),
            'prev_sibling'             => $siblings['prev'],
            'next_sibling'             => $siblings['next'],
            'item_depths'              => $itemDepths,
            'item_parent_labels'       => $itemParentLabels,
            'dashboard_show_route'     => self::ROUTE_SHOW,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
            'stimulus_script_url'      => $this->stimulusScriptUrl,
            'position_step'            => $this->positionStep,
        ]);
    }

    #[Route(path: '/{id}/items/reindex-positions', name: 'items_reindex_positions', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function itemsReindexPositions(Request $request, int $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('reindex_positions_' . $id, $request->request->getString('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $changed = $this->menuItemRepository->reindexPositionsWithStep($menu, $this->positionStep);
        $this->entityManager->flush();
        if ($changed > 0) {
            $this->addFlash(
                'success',
                $this->translator->trans('dashboard.reindex_positions_done', ['%step%' => (string) $this->positionStep], NowoDashboardMenuBundle::TRANSLATION_DOMAIN),
            );
        } else {
            $this->addFlash(
                'info',
                $this->translator->trans('dashboard.reindex_positions_unchanged', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN),
            );
        }

        return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
    }

    #[Route(path: '/{id}/items/check-parent-cycles', name: 'items_check_parent_cycles', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function itemsCheckParentCycles(Request $request, int $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('check_parent_cycles_' . $id, $request->request->getString('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $cycle = $this->menuItemRepository->findFirstParentIdCycleChain($menu);
        if ($cycle === null) {
            $this->addFlash(
                'success',
                $this->translator->trans('dashboard.check_parent_cycles_ok', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN),
            );
        } else {
            $chain = implode(' → ', array_map(static fn (int $i): string => (string) $i, $cycle));
            if ($cycle !== []) {
                $chain .= ' → ' . (string) $cycle[0];
            }
            $this->addFlash(
                'error',
                $this->translator->trans('dashboard.check_parent_cycles_loop', ['%chain%' => $chain], NowoDashboardMenuBundle::TRANSLATION_DOMAIN),
            );
        }

        return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
    }

    #[Route(path: '/{id}/items/reorder-tree', name: 'items_reorder_tree', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function itemsReorderTree(Request $request, int $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('reorder_tree_' . $id, $request->request->getString('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $raw = $request->request->getString('tree');
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->addFlash(
                'error',
                $this->translator->trans('dashboard.reorder_tree_invalid_json', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN),
            );

            return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            $this->addFlash(
                'error',
                $this->translator->trans('dashboard.reorder_tree_invalid_payload', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN),
            );

            return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
        }
        /** @var list<array{id: int, parent_id: int|null, position: int}> $nodes */
        $nodes = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('dashboard.reorder_tree_invalid_payload', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN),
                );

                return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
            }
            $itemId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($itemId <= 0) {
                $this->addFlash(
                    'error',
                    $this->translator->trans('dashboard.reorder_tree_invalid_payload', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN),
                );

                return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
            }
            $parentRaw = $row['parent_id'] ?? null;
            $parentId  = null;
            if ($parentRaw !== null && $parentRaw !== '') {
                $parentId = (int) $parentRaw;
            }
            $position = isset($row['position']) ? (int) $row['position'] : 0;
            $nodes[]  = ['id' => $itemId, 'parent_id' => $parentId, 'position' => $position];
        }
        try {
            $this->menuItemRepository->applyTreeLayout($menu, $nodes, $this->positionStep);
        } catch (InvalidArgumentException $e) {
            $flashKey = $e->getMessage() === MenuItemRepository::TREE_LAYOUT_SECTION_MUST_BE_ROOT
                ? 'dashboard.reorder_tree_section_not_root'
                : 'dashboard.reorder_tree_invalid_layout';
            $this->addFlash(
                'error',
                $this->translator->trans($flashKey, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN),
            );

            return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
        }
        $this->entityManager->flush();
        $this->addFlash(
            'success',
            $this->translator->trans('dashboard.reorder_tree_done', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN),
        );

        return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
    }

    /**
     * @param list<MenuItem> $items Items for one menu (any order)
     *
     * @return list<array{item: MenuItem, children: list<array{item: MenuItem, children: array}>}>
     */
    private function buildMenuItemTreeForSortable(array $items): array
    {
        /** @var array<string, list<MenuItem>> $byParent */
        $byParent = [];
        foreach ($items as $item) {
            $parentId = $item->getParent()?->getId();
            $key      = $parentId !== null ? (string) $parentId : '__root';
            if (!isset($byParent[$key])) {
                $byParent[$key] = [];
            }
            $byParent[$key][] = $item;
        }

        foreach ($byParent as &$siblings) {
            usort($siblings, static function (MenuItem $a, MenuItem $b): int {
                $diff = $a->getPosition() <=> $b->getPosition();
                if ($diff !== 0) {
                    return $diff;
                }

                return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
            });
        }
        unset($siblings);

        $build = static function (string $parentKey) use (&$build, $byParent): array {
            $out = [];
            foreach ($byParent[$parentKey] ?? [] as $item) {
                $itemId   = $item->getId();
                $children = $itemId !== null ? $build((string) $itemId) : [];

                $out[] = [
                    'item'     => $item,
                    'children' => $children,
                ];
            }

            return $out;
        };

        return $build('__root');
    }

    /**
     * @param list<MenuItem> $items Flat list ordered by parent then position
     *
     * @return array<int, int> Map item id => depth (0 = root, 1 = first level, etc.)
     */
    private function computeItemDepths(array $items): array
    {
        $depths = [];
        foreach ($items as $item) {
            $id = $item->getId();
            if ($id === null) {
                continue;
            }
            $depth = 0;
            $p     = $item->getParent();
            while ($p !== null) {
                ++$depth;
                $p = $p->getParent();
            }
            $depths[$id] = $depth;
        }

        return $depths;
    }

    /**
     * @param list<MenuItem> $items Flat list ordered by parent then position
     *
     * @return array{prev: array<int|string, int|null>, next: array<int|string, int|null>}
     */
    private function computeSiblingMaps(array $items): array
    {
        $byParent = [];
        foreach ($items as $item) {
            $pid = $item->getParent()?->getId() ?? -1;
            if (!isset($byParent[$pid])) {
                $byParent[$pid] = [];
            }
            $byParent[$pid][] = $item;
        }
        $prev = [];
        $next = [];
        foreach ($byParent as $group) {
            foreach ($group as $i => $item) {
                $prev[$item->getId()] = $i > 0 ? $group[$i - 1]->getId() : null;
                $next[$item->getId()] = $i < count($group) - 1 ? $group[$i + 1]->getId() : null;
            }
        }

        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * @param list<MenuItem> $items
     *
     * @return list<MenuItem>
     */
    private function orderItemsByTreeForDisplay(array $items): array
    {
        /** @var array<string, list<MenuItem>> $byParent */
        $byParent = [];
        foreach ($items as $item) {
            $parentId = $item->getParent()?->getId();
            $key      = $parentId !== null ? (string) $parentId : '__root';
            if (!isset($byParent[$key])) {
                $byParent[$key] = [];
            }
            $byParent[$key][] = $item;
        }

        foreach ($byParent as &$siblings) {
            usort($siblings, static function (MenuItem $a, MenuItem $b): int {
                $diff = $a->getPosition() <=> $b->getPosition();
                if ($diff !== 0) {
                    return $diff;
                }

                return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
            });
        }
        unset($siblings);

        $ordered = [];
        $walk    = static function (string $parentKey) use (&$walk, &$ordered, $byParent): void {
            foreach ($byParent[$parentKey] ?? [] as $item) {
                $ordered[] = $item;
                $itemId    = $item->getId();
                if ($itemId !== null) {
                    $walk((string) $itemId);
                }
            }
        };
        $walk('__root');

        return $ordered;
    }

    /**
     * @param list<MenuItem> $items Flat list ordered by parent then position
     *
     * @return array<int, string> Map item id => parent label (or the translated root label, e.g. "— Root —", for root)
     */
    private function computeParentLabels(array $items, string $locale = 'en'): array
    {
        $labels = [];
        foreach ($items as $item) {
            $id = $item->getId();
            if ($id === null) {
                continue;
            }
            $parent      = $item->getParent();
            $labels[$id] = $parent !== null ? $parent->getLabelForLocale($locale) : $this->translator->trans('dashboard.root', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN);
        }

        return $labels;
    }

    #[Route(path: '/{id}/item/{itemId}/move-up', name: 'item_move_up', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['POST'])]
    public function itemMoveUp(Request $request, int $id, int $itemId): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('item_move_up_' . $itemId, $request->request->getString('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $item = $this->menuItemRepository->find($itemId);
        if ($item === null || $item->getMenu() !== $menu) {
            $this->addFlash('error', 'Item not found.');

            return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id]);
        }
        $siblings = $this->menuItemRepository->findSiblingsByPosition($item);
        $idx      = null;
        foreach ($siblings as $i => $s) {
            if ($s->getId() === $item->getId()) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null || $idx === 0) {
            $this->addFlash('info', 'Item is already first among its siblings.');

            return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id]);
        }
        $prev    = $siblings[$idx - 1];
        $itemPos = $item->getPosition();
        $prevPos = $prev->getPosition();
        $item->setPosition($prevPos);
        $prev->setPosition($itemPos);
        $this->entityManager->flush();
        $this->addFlash('success', 'Item moved up.');

        return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id], 'item-' . $itemId);
    }

    #[Route(path: '/{id}/item/{itemId}/move-down', name: 'item_move_down', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['POST'])]
    public function itemMoveDown(Request $request, int $id, int $itemId): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('item_move_down_' . $itemId, $request->request->getString('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $item = $this->menuItemRepository->find($itemId);
        if ($item === null || $item->getMenu() !== $menu) {
            $this->addFlash('error', 'Item not found.');

            return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id]);
        }
        $siblings = $this->menuItemRepository->findSiblingsByPosition($item);
        $idx      = null;
        foreach ($siblings as $i => $s) {
            if ($s->getId() === $item->getId()) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null || $idx === count($siblings) - 1) {
            $this->addFlash('info', 'Item is already last among its siblings.');

            return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id]);
        }
        $next    = $siblings[$idx + 1];
        $itemPos = $item->getPosition();
        $nextPos = $next->getPosition();
        $item->setPosition($nextPos);
        $next->setPosition($itemPos);
        $this->entityManager->flush();
        $this->addFlash('success', 'Item moved down.');

        return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id], 'item-' . $itemId);
    }

    #[Route(path: '/{id}/edit', name: 'menu_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editMenu(Request $request, int $id): Response
    {
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $sectionFocus = in_array($request->query->get('section'), ['basic', 'config'], true) ? $request->query->get('section') : null;
        if ($sectionFocus === null && $request->isMethod('POST')) {
            $sectionFocus = in_array($request->request->get('_section'), ['basic', 'config'], true) ? $request->request->get('_section') : null;
        }
        $wasBase      = $menu->isBase();
        $menuSnapshot = $this->snapshotMenuForBaseProtection($menu);
        $originalCode = $menu->getCode();
        $form         = $this->createForm(MenuType::class, $menu, [
            'action'  => $this->generateUrl(self::ROUTE_MENU_EDIT, ['id' => $id]),
            'section' => $sectionFocus,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($wasBase) {
                // Base menus are immutable: the only allowed edit is unchecking "base".
                $unsetBase = !$menu->isBase();
                $this->restoreMenuFromSnapshot($menu, $menuSnapshot);
                $menu->setBase(!$unsetBase);
            }
            if ($menu->isBase()) {
                $menu->setCode($originalCode);
            }
            $existing = $this->menuRepository->findOneByCodeAndContext($menu->getCode(), $menu->getContext());
            if ($existing instanceof Menu && $existing->getId() !== $menu->getId()) {
                $form->addError(new FormError($this->translator->trans('dashboard.unique_code_context_error', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN)));
            } else {
                $this->entityManager->flush();
                $this->addFlash('success', 'Menu updated.');

                return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
            }
        }

        if ($request->query->get('_partial')) {
            return $this->render('@NowoDashboardMenuBundle/dashboard/_menu_form_partial.html.twig', [
                'form'             => $form,
                'is_edit'          => true,
                'dashboard_routes' => $this->getDashboardRoutes(),
                'section_focus'    => $sectionFocus,
            ]);
        }

        return $this->render('@NowoDashboardMenuBundle/dashboard/menu_form.html.twig', [
            'form'                     => $form,
            'is_edit'                  => true,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
            'stimulus_script_url'      => $this->stimulusScriptUrl,
        ]);
    }

    #[Route(path: '/{id}/delete', name: 'menu_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteMenu(Request $request, int $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('delete_menu_' . $id, $request->request->getString('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        if ($menu->isBase()) {
            $this->addFlash('error', 'Base menus cannot be deleted.');

            return $this->redirectToRefererOr($request, self::ROUTE_INDEX, []);
        }
        $em = $this->entityManager;
        $em->remove($menu);
        $em->flush();
        $this->addFlash('success', 'Menu deleted.');

        return $this->redirectToRefererOr($request, self::ROUTE_INDEX, []);
    }

    #[Route(path: '/export', name: 'export_all', methods: ['GET'])]
    public function exportAll(Request $request): StreamedResponse
    {
        $this->importExportRateLimiter->consume($this->getRateLimitKey($request));
        $data = $this->menuExporter->exportAll();
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $response = new StreamedResponse(static function () use ($json): void {
            echo $json;
        });
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="dashboard-menus-export.json"');

        return $response;
    }

    #[Route(path: '/{id}/export', name: 'export_menu', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function exportMenu(Request $request, int $id): StreamedResponse|Response
    {
        $this->importExportRateLimiter->consume($this->getRateLimitKey($request));
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $data = $this->menuExporter->exportMenu($menu);
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $safeCode = preg_replace('/[^a-zA-Z0-9_-]/', '_', $menu->getCode());
        $filename = 'menu-' . $safeCode . '-export.json';

        $response = new StreamedResponse(static function () use ($json): void {
            echo $json;
        });
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route(path: '/import', name: 'import', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        $form = $this->createForm(ImportMenuType::class, null, [
            'action' => $this->generateUrl(self::ROUTE_IMPORT),
        ]);
        $form->handleRequest($request);
        $isModal = $request->request->has('_modal') || $request->query->get('_partial');

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $file = $data['file'] ?? null;
            if ($file instanceof UploadedFile) {
                $this->importExportRateLimiter->consume($this->getRateLimitKey($request));
                $size = $file->getSize();
                if ($size !== false && $size > $this->importMaxBytes) {
                    $this->addFlash('error', $this->translator->trans('dashboard.import_file_too_large', [
                        '%max%' => (string) (int) ($this->importMaxBytes / 1024 / 1024),
                    ], NowoDashboardMenuBundle::TRANSLATION_DOMAIN));

                    return $this->renderImportResponse($request, $form, $isModal);
                }
                try {
                    $content = $file->getContent();
                    $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    $this->addFlash('error', $this->translator->trans('dashboard.import_json_error', [
                        '%message%' => $e->getMessage(),
                    ], NowoDashboardMenuBundle::TRANSLATION_DOMAIN));

                    return $this->renderImportResponse($request, $form, $isModal);
                }
                if (!is_array($decoded)) {
                    $this->addFlash('error', $this->translator->trans('dashboard.import_json_invalid', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN));
                } else {
                    $decoded      = MenuImporter::normalizeImportPayload($decoded);
                    $formatErrors = $this->validateImportPayloadFormat($decoded);
                    if ($formatErrors !== []) {
                        foreach ($formatErrors as $formatError) {
                            $this->addFlash('error', $formatError);
                        }

                        return $this->redirectToRefererOr($request, self::ROUTE_INDEX, []);
                    }
                    $strategy = is_string($data['strategy'] ?? '') ? $data['strategy'] : MenuImporter::STRATEGY_SKIP_EXISTING;
                    $result   = $this->menuImporter->import($decoded, $strategy);
                    foreach ($result['errors'] as $err) {
                        $this->addFlash('error', $err);
                    }
                    if ($result['errors'] === []) {
                        $msg = $this->translator->trans('dashboard.import_done', [
                            '%created%' => $result['created'],
                            '%updated%' => $result['updated'],
                            '%skipped%' => $result['skipped'],
                        ], NowoDashboardMenuBundle::TRANSLATION_DOMAIN);
                        $this->addFlash('success', $msg);
                    }

                    return $this->redirectToRefererOr($request, self::ROUTE_INDEX, []);
                }
            }

            return $this->redirectToRefererOr($request, self::ROUTE_INDEX, []);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->renderImportResponse($request, $form, $isModal);
        }

        return $this->renderImportResponse($request, $form, $isModal);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function renderImportResponse(Request $request, FormInterface $form, bool $usePartial): Response
    {
        $vars = [
            'form'             => $form,
            'dashboard_routes' => $this->getDashboardRoutes(),
        ];
        if ($usePartial || $request->query->get('_partial')) {
            return $this->render('@NowoDashboardMenuBundle/dashboard/_import_partial.html.twig', $vars);
        }

        return $this->render('@NowoDashboardMenuBundle/dashboard/import.html.twig', $vars);
    }

    /**
     * @param array<mixed> $decoded
     *
     * @return list<string>
     */
    private function validateImportPayloadFormat(array $decoded): array
    {
        if (isset($decoded['menu']) && is_array($decoded['menu']) && isset($decoded['items']) && is_array($decoded['items'])) {
            return [];
        }
        if (isset($decoded['menus']) && is_array($decoded['menus'])) {
            return [];
        }
        if (array_is_list($decoded)) {
            return [$this->translator->trans('dashboard.import_format_invalid_root_array', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN)];
        }

        return [$this->translator->trans('dashboard.import_format_expected', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN)];
    }

    #[Route(path: '/{id}/copy', name: 'menu_copy', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function copyMenu(Request $request, int $id): Response
    {
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $defaults = [
            'code' => $menu->getCode() . '_copy',
            'name' => ($menu->getName() ?? $menu->getCode()) . ' (copy)',
        ];
        $form = $this->createForm(CopyMenuType::class, $defaults, [
            'action' => $this->generateUrl(self::ROUTE_MENU_COPY, ['id' => $id]),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data       = $form->getData();
            $newCode    = trim((string) $data['code']);
            $newName    = isset($data['name']) && $data['name'] !== '' ? trim((string) $data['name']) : null;
            $newContext = $menu->getContext();
            $existing   = $this->menuRepository->findOneByCodeAndContext($newCode, $newContext);
            if ($existing instanceof Menu) {
                $this->addFlash('error', 'A menu with this code and context already exists.');

                return $this->render('@NowoDashboardMenuBundle/dashboard/copy_menu.html.twig', [
                    'menu'                     => $menu,
                    'form'                     => $form,
                    'dashboard_show_route'     => self::ROUTE_SHOW,
                    'dashboard_routes'         => $this->getDashboardRoutes(),
                    'icon_selector_script_url' => $this->iconSelectorScriptUrl,
                    'stimulus_script_url'      => $this->stimulusScriptUrl,
                ]);
            }
            $copy = $this->cloneMenuWithItems($menu, $newCode, $newName);
            $this->addFlash('success', 'Menu copied successfully.');

            return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $copy->getId()]);
        }

        if ($request->query->get('_partial')) {
            return $this->render('@NowoDashboardMenuBundle/dashboard/_copy_menu_partial.html.twig', [
                'menu' => $menu,
                'form' => $form,
            ]);
        }

        return $this->render('@NowoDashboardMenuBundle/dashboard/copy_menu.html.twig', [
            'menu'                     => $menu,
            'form'                     => $form,
            'dashboard_show_route'     => self::ROUTE_SHOW,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
            'stimulus_script_url'      => $this->stimulusScriptUrl,
        ]);
    }

    /**
     * Clones a menu and all its items (recursively). New menu gets the given code and name; all other fields (including context) are copied from source.
     */
    private function cloneMenuWithItems(Menu $source, string $newCode, ?string $newName): Menu
    {
        $copy = new Menu();
        $copy->setCode($newCode);
        $copy->setName($newName);
        $copy->setIcon($source->getIcon());
        $copy->setClassMenu($source->getClassMenu());
        $copy->setUlId($source->getUlId());
        $copy->setClassItem($source->getClassItem());
        $copy->setClassLink($source->getClassLink());
        $copy->setClassChildren($source->getClassChildren());
        $copy->setClassSection($source->getClassSection());
        $copy->setClassDivider($source->getClassDivider());
        $copy->setClassCurrent($source->getClassCurrent());
        $copy->setClassBranchExpanded($source->getClassBranchExpanded());
        $copy->setClassHasChildren($source->getClassHasChildren());
        $copy->setClassExpanded($source->getClassExpanded());
        $copy->setClassCollapsed($source->getClassCollapsed());
        $copy->setPermissionChecker($source->getPermissionChecker());
        $copy->setDepthLimit($source->getDepthLimit());
        $copy->setCollapsible($source->getCollapsible());
        $copy->setCollapsibleExpanded($source->getCollapsibleExpanded());
        $copy->setNestedCollapsible($source->getNestedCollapsible());
        $copy->setContext($source->getContext() ?? null);
        $copy->setBase(false);

        $em = $this->entityManager;
        $em->persist($copy);
        $em->flush();

        $items = $this->menuItemRepository->findAllForMenuOrderedByTreeForExport($source);
        /** @var array<int, MenuItem> $sourceToCopyById */
        $sourceToCopyById = [];

        // First pass: clone scalar fields (without parent relation).
        foreach ($items as $sourceItem) {
            $newItem = new MenuItem();
            $newItem->setMenu($copy);
            $newItem->setPosition($sourceItem->getPosition());
            $newItem->setLabel($sourceItem->getLabel());
            $newItem->setTranslations($sourceItem->getTranslations());
            $newItem->setItemType($sourceItem->getItemType());
            $newItem->setLinkType($sourceItem->getLinkType());
            $newItem->setRouteName($sourceItem->getRouteName());
            $newItem->setRouteParams($sourceItem->getRouteParams());
            $newItem->setExternalUrl($sourceItem->getExternalUrl());
            $newItem->setIcon($sourceItem->getIcon());
            $newItem->setPermissionKeys($sourceItem->getPermissionKeys());
            $newItem->setIsUnanimous($sourceItem->isUnanimous());
            $newItem->setTargetBlank($sourceItem->getTargetBlank());
            $em->persist($newItem);

            $sourceId = $sourceItem->getId();
            if ($sourceId !== null) {
                $sourceToCopyById[$sourceId] = $newItem;
            }
        }

        // Second pass: wire parent relation using in-memory map.
        foreach ($items as $sourceItem) {
            $sourceId = $sourceItem->getId();
            if ($sourceId === null || !isset($sourceToCopyById[$sourceId])) {
                continue;
            }
            $copyItem = $sourceToCopyById[$sourceId];
            $parentId = $sourceItem->getParent()?->getId();
            $copyItem->setParent($parentId !== null ? ($sourceToCopyById[$parentId] ?? null) : null);
        }

        $em->flush();

        return $copy;
    }

    #[Route(path: '/{id}/item/new', name: 'item_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    /**
     * Creates a new menu item.
     *
     * Supports both:
     * - Full page rendering
     * - Modal partial rendering via `?_partial=1`
     *
     * When creating a child item, the UI shows only `label` + per-locale translations
     * (item type is fixed to Link in the form).
     *
     * @param Request $request Incoming request (GET/POST)
     * @param int $id Dashboard menu id
     *
     * @return Response Rendered page/partial or redirect on success
     */
    public function newItem(Request $request, int $id): Response
    {
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $item = new MenuItem();
        $item->setMenu($menu);
        $parentId = $request->query->getInt('parent');
        if ($parentId > 0) {
            $parent = $this->menuItemRepository->find($parentId);
            if ($parent !== null && $parent->getMenu() === $menu) {
                $item->setParent($parent);
            }
        }
        $appRoutes = $this->getAppRoutes();
        $locale    = $request->getLocale();
        $this->generateUrl(self::ROUTE_SHOW, ['id' => $id]);
        $parent    = $item->getParent();
        $isChild   = $parent instanceof MenuItem;
        $actionUrl = $parent instanceof MenuItem && $parent->getId() !== null
            ? $this->generateUrl(self::ROUTE_ITEM_NEW, ['id' => $id, '_query' => ['parent' => $parent->getId()]])
            : $this->generateUrl(self::ROUTE_ITEM_NEW, ['id' => $id]);

        if ($request->query->get('_partial')) {
            // "Add item" uses a normal Symfony form (not LiveComponent) to avoid translation save issues.
            $sectionFocus        = $isChild ? 'basic' : 'identity';
            $formSection         = $isChild ? 'basic' : 'identity';
            $includeTranslations = $isChild;
            $form                = $this->createForm(MenuItemType::class, $item, [
                'app_routes'        => $appRoutes,
                'menu'              => $menu,
                'exclude_ids'       => [],
                'locale'            => $locale,
                'available_locales' => $this->locales,
                'action'            => $actionUrl,
                // Keep CSRF consistent across Symfony versions.
                'csrf_token_id'        => 'submit',
                'section'              => $formSection,
                'include_translations' => $includeTranslations,
            ]);

            return $this->render('@NowoDashboardMenuBundle/dashboard/_item_form_partial.html.twig', [
                'form'              => $form,
                'menu'              => $menu,
                'is_edit'           => false,
                'app_routes'        => $appRoutes,
                'locales'           => $this->locales,
                'item_has_children' => false,
                'section_focus'     => $sectionFocus,
                'show_translations' => $includeTranslations,
            ]);
        }

        $form = $this->createForm(MenuItemType::class, $item, [
            'app_routes'        => $appRoutes,
            'menu'              => $menu,
            'exclude_ids'       => [],
            'locale'            => $locale,
            'available_locales' => $this->locales,
            'action'            => $actionUrl,
            // Keep CSRF consistent across Symfony versions.
            'csrf_token_id'        => 'submit',
            'section'              => $isChild ? 'basic' : 'identity',
            'include_translations' => $isChild,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $item->setMenu($menu);
            if ($item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
                $item->normalizeDividerState();
            }

            // Append new items at the end of their sibling group.
            // This avoids `position = 0` for every new element.
            $maxPosition = $this->menuItemRepository->findMaxPositionForParent($menu, $item->getParent());
            $item->setPosition($maxPosition + 1);

            $this->entityManager->persist($item);
            $this->entityManager->flush();

            // If existing siblings have duplicated (e.g. all `0`) positions, reindex them
            // so the table ordering becomes deterministic.
            if ($this->reindexSiblingPositionsIfNeeded($menu, $item->getParent())) {
                $this->entityManager->flush();
            }

            $this->addFlash('success', 'Item created.');

            return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
        }

        return $this->render('@NowoDashboardMenuBundle/dashboard/item_form.html.twig', [
            'menu'                     => $menu,
            'form'                     => $form,
            'is_edit'                  => false,
            'app_routes'               => $appRoutes,
            'locales'                  => $this->locales,
            'show_translations'        => $isChild,
            'dashboard_show_route'     => self::ROUTE_SHOW,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
            'stimulus_script_url'      => $this->stimulusScriptUrl,
            'item_has_children'        => false,
        ]);
    }

    #[Route(path: '/{id}/item/{itemId}/edit', name: 'item_edit', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['GET', 'POST'])]
    /**
     * Updates an existing menu item.
     *
     * Supports both full page rendering and modal partial rendering via `?_partial=1`.
     * The `section` / `_section` parameter controls which part of the item form is built:
     * - `basic`/`identity`: labels and per-locale translations
     * - `icon`: item type + position + icon
     * - `config`: position + link + permission options
     *
     * @param Request $request Incoming request (GET/POST)
     * @param int $id Dashboard menu id
     * @param int $itemId Item id
     *
     * @return Response Rendered page/partial or redirect on success
     */
    public function editItem(Request $request, int $id, int $itemId): Response
    {
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $item = $this->menuItemRepository->find($itemId);
        if ($item === null || $item->getMenu() !== $menu) {
            $this->addFlash('error', 'Item not found. It may have been deleted or the menu was reset.');

            return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id]);
        }
        $appRoutes        = $this->getAppRoutesForItem($item, $this->getAppRoutes());
        $locale           = $request->getLocale();
        $itemHasChildren  = $item->getChildren()->count() > 0;
        $originalParent   = $item->getParent();
        $originalParentId = $originalParent?->getId();
        $sectionFocus     = in_array($request->query->get('section'), ['basic', 'icon', 'config', 'identity'], true)
            ? $request->query->get('section')
            : null;
        if ($sectionFocus === null && $request->isMethod('POST')) {
            $sectionFocus = in_array($request->request->get('_section'), ['basic', 'icon', 'config', 'identity'], true)
                ? $request->request->get('_section')
                : null;
        }

        if ($request->query->get('_partial')) {
            // Icon section uses a normal Symfony form to ensure the icon-selector widget refreshes correctly
            // when the modal content changes.
            $useLiveComponent = $this->itemFormLiveComponentEnabled && $sectionFocus === 'config';
            if ($useLiveComponent) {
                return $this->render('@NowoDashboardMenuBundle/dashboard/_item_form_live_partial.html.twig', [
                    'menu'              => $menu,
                    'item'              => $item,
                    'app_routes'        => $appRoutes,
                    'exclude_ids'       => [],
                    'locale'            => $locale,
                    'is_edit'           => true,
                    'item_has_children' => $itemHasChildren,
                    'action_url'        => $this->generateUrl(self::ROUTE_ITEM_EDIT, ['id' => $id, 'itemId' => $itemId]),
                    'redirect_to_url'   => $this->generateUrl(self::ROUTE_SHOW, ['id' => $id]),
                    'locales'           => $this->locales,
                    'section_focus'     => $sectionFocus,
                ]);
            }
            $form = $this->createForm(MenuItemType::class, $item, [
                'app_routes'        => $appRoutes,
                'menu'              => $menu,
                'exclude_ids'       => [],
                'locale'            => $locale,
                'available_locales' => $this->locales,
                'action'            => $this->generateUrl(self::ROUTE_ITEM_EDIT, ['id' => $id, 'itemId' => $itemId]),
                // Keep CSRF consistent across Symfony versions.
                'csrf_token_id' => 'submit',
                'section'       => $sectionFocus,
            ]);

            return $this->render('@NowoDashboardMenuBundle/dashboard/_item_form_partial.html.twig', [
                'form'              => $form,
                'menu'              => $menu,
                'is_edit'           => true,
                'app_routes'        => $appRoutes,
                'locales'           => $this->locales,
                'item_has_children' => $itemHasChildren,
                'section_focus'     => $sectionFocus,
            ]);
        }

        $form = $this->createForm(MenuItemType::class, $item, [
            'app_routes'        => $appRoutes,
            'menu'              => $menu,
            'exclude_ids'       => [],
            'locale'            => $locale,
            'available_locales' => $this->locales,
            'action'            => $this->generateUrl(self::ROUTE_ITEM_EDIT, ['id' => $id, 'itemId' => $itemId]),
            // Keep CSRF consistent across Symfony versions.
            'csrf_token_id' => 'submit',
            'section'       => $sectionFocus,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
                $item->normalizeDividerState();
            }
            if ($item->getItemType() === MenuItem::ITEM_TYPE_LINK && $item->getChildren()->count() > 0) {
                $item->setLinkType(null);
                $item->setRouteName(null);
                $item->setRouteParams(null);
                $item->setExternalUrl(null);
            }

            $parentChanged = $originalParentId !== $item->getParent()?->getId();
            // When moving an item between parents via the config (gear) section,
            // the position field isn't part of that form submission, so we append it.
            if ($sectionFocus === 'config' && $parentChanged) {
                $maxPosition = $this->menuItemRepository->findMaxPositionForParent($menu, $item->getParent());
                $item->setPosition($maxPosition + 1);
            }

            // Guard against partial / unmapped form listeners: update translations directly
            // from Symfony form field values when editing labels identity.
            if (($sectionFocus === 'basic' || $sectionFocus === 'identity') && $form->has('basic')) {
                $existingTranslations = $item->getTranslations() ?? [];
                $foundAny             = false;
                $basicForm            = $form->get('basic');
                foreach ($this->locales as $locale) {
                    $fieldName = 'label_' . $locale;
                    if (!$basicForm->has($fieldName)) {
                        continue;
                    }

                    $foundAny = true;
                    $value    = $basicForm->get($fieldName)->getData();
                    if ($value === null || trim((string) $value) === '') {
                        unset($existingTranslations[$locale]);
                        continue;
                    }

                    $existingTranslations[$locale] = (string) $value;
                }
                if ($foundAny) {
                    $item->setTranslations($existingTranslations === [] ? null : $existingTranslations);
                }
            }

            $this->entityManager->flush();

            $changed = $this->reindexSiblingPositionsIfNeeded($menu, $item->getParent());
            if ($parentChanged) {
                $changed = $this->reindexSiblingPositionsIfNeeded($menu, $originalParent) || $changed;
            }
            if ($changed) {
                $this->entityManager->flush();
            }

            $this->addFlash('success', 'Item updated.');

            return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
        }

        return $this->render('@NowoDashboardMenuBundle/dashboard/item_form.html.twig', [
            'menu'                     => $menu,
            'form'                     => $form,
            'is_edit'                  => true,
            'app_routes'               => $appRoutes,
            'locales'                  => $this->locales,
            'dashboard_show_route'     => self::ROUTE_SHOW,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
            'stimulus_script_url'      => $this->stimulusScriptUrl,
            'item_has_children'        => $itemHasChildren,
        ]);
    }

    #[Route(path: '/{id}/item/{itemId}/delete', name: 'item_delete', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['POST'])]
    public function deleteItem(Request $request, int $id, int $itemId): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('delete_item_' . $itemId, $request->request->getString('_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $item = $this->menuItemRepository->find($itemId);
        if ($item === null || $item->getMenu() !== $menu) {
            $this->addFlash('error', 'Item not found. It may have been deleted or the menu was reset.');

            return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id]);
        }
        $em = $this->entityManager;
        $em->remove($item);
        $em->flush();
        $this->addFlash('success', 'Item deleted.');

        return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
    }

    /**
     * @return array<string, array{label: string, params: list<string>}> Map of route name => { label, params }
     */
    private function getAppRoutes(): array
    {
        $routes     = [];
        $collection = $this->router->getRouteCollection();
        foreach ($collection as $name => $route) {
            if ($this->isRouteNameExcluded($name)) {
                continue;
            }
            $path   = $route->getPath();
            $label  = $path !== '/' ? $name . ' (' . $path . ')' : $name;
            $params = [];
            if (preg_match_all('/\{(\w+)\}/', $path, $m)) {
                $params = array_values(array_unique($m[1]));
            }
            $routes[$name] = ['label' => $label, 'params' => $params];
        }
        ksort($routes, SORT_NATURAL);

        return $routes;
    }

    private function isRouteNameExcluded(string $routeName): bool
    {
        foreach ($this->routeNameExcludePatterns as $pattern) {
            $p = trim((string) $pattern);
            if ($p === '') {
                continue;
            }

            // Support both full PCRE patterns (e.g. "#^web_profiler#") and "regex snippets"
            // (e.g. "^web_profiler") by retrying with a delimiter when preg_match fails.
            $matched = @preg_match($p, $routeName);
            if ($matched === 1) {
                return true;
            }

            // If it's already a delimited regex and didn't match, don't retry.
            if ($matched !== false) {
                continue;
            }

            $first       = $p[0] ?? '';
            $last        = $p[strlen($p) - 1] ?? '';
            $isDelimited = $first !== '' && $first === $last && in_array($first, ['/', '#', '~', '%', '@', '!'], true);
            if ($isDelimited) {
                continue;
            }

            $wrapped = '#' . str_replace('#', '\\#', $p) . '#';
            if (@preg_match($wrapped, $routeName) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{label: string, params: list<string>}> $appRoutes
     *
     * @return array<string, array{label: string, params: list<string>}>
     */
    private function getAppRoutesForItem(MenuItem $item, array $appRoutes): array
    {
        $current = $item->getRouteName();
        if ($current !== null && $current !== '' && !isset($appRoutes[$current])) {
            $appRoutes[$current] = ['label' => $current . ' (current)', 'params' => []];
            ksort($appRoutes, SORT_NATURAL);
        }

        return $appRoutes;
    }

    /**
     * Reindexes positions within the given sibling group when duplicates exist.
     *
     * This makes ordering deterministic (useful when existing data has all `position = 0`).
     *
     * @return bool true if any position was modified
     */
    private function reindexSiblingPositionsIfNeeded(Menu $menu, ?MenuItem $parent): bool
    {
        $dummy = new MenuItem();
        $dummy->setMenu($menu);
        $dummy->setParent($parent);

        $siblings = $this->menuItemRepository->findSiblingsByPosition($dummy);
        if ($siblings === []) {
            return false;
        }

        $uniquePositions = [];
        foreach ($siblings as $sibling) {
            $uniquePositions[$sibling->getPosition()] = true;
        }

        // If every position is unique, keep the user's ordering (gaps don't matter).
        if (count($uniquePositions) === count($siblings)) {
            return false;
        }

        foreach ($siblings as $i => $sibling) {
            $sibling->setPosition($i);
        }

        return true;
    }

    private function getRateLimitKey(Request $request): string
    {
        try {
            $user = $this->getUser();
            if ($user instanceof UserInterface) {
                return 'user:' . $user->getUserIdentifier();
            }
        } catch (Throwable) {
            // No SecurityBundle or no token storage: use IP only
        }

        return 'ip:' . ($request->getClientIp() ?? 'anon');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotMenuForBaseProtection(Menu $menu): array
    {
        return [
            'code'                      => $menu->getCode(),
            'name'                      => $menu->getName(),
            'context'                   => $menu->getContext(),
            'icon'                      => $menu->getIcon(),
            'classMenu'                 => $menu->getClassMenu(),
            'ulId'                      => $menu->getUlId(),
            'classItem'                 => $menu->getClassItem(),
            'classLink'                 => $menu->getClassLink(),
            'classChildren'             => $menu->getClassChildren(),
            'classSectionLabel'         => $menu->getClassSectionLabel(),
            'classSection'              => $menu->getClassSection(),
            'classDivider'              => $menu->getClassDivider(),
            'classCurrent'              => $menu->getClassCurrent(),
            'classBranchExpanded'       => $menu->getClassBranchExpanded(),
            'classHasChildren'          => $menu->getClassHasChildren(),
            'classExpanded'             => $menu->getClassExpanded(),
            'classCollapsed'            => $menu->getClassCollapsed(),
            'permissionChecker'         => $menu->getPermissionChecker(),
            'depthLimit'                => $menu->getDepthLimit(),
            'collapsible'               => $menu->getCollapsible(),
            'collapsibleExpanded'       => $menu->getCollapsibleExpanded(),
            'nestedCollapsible'         => $menu->getNestedCollapsible(),
            'nestedCollapsibleSections' => $menu->getNestedCollapsibleSections(),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function restoreMenuFromSnapshot(Menu $menu, array $snapshot): void
    {
        $menu->setCode((string) ($snapshot['code'] ?? ''));
        $menu->setName(is_string($snapshot['name'] ?? null) ? $snapshot['name'] : null);
        $menu->setContext(is_array($snapshot['context'] ?? null) ? $snapshot['context'] : null);
        $menu->setIcon(is_string($snapshot['icon'] ?? null) ? $snapshot['icon'] : null);
        $menu->setClassMenu(is_string($snapshot['classMenu'] ?? null) ? $snapshot['classMenu'] : null);
        $menu->setUlId(is_string($snapshot['ulId'] ?? null) ? $snapshot['ulId'] : null);
        $menu->setClassItem(is_string($snapshot['classItem'] ?? null) ? $snapshot['classItem'] : null);
        $menu->setClassLink(is_string($snapshot['classLink'] ?? null) ? $snapshot['classLink'] : null);
        $menu->setClassChildren(is_string($snapshot['classChildren'] ?? null) ? $snapshot['classChildren'] : null);
        $menu->setClassSectionLabel(is_string($snapshot['classSectionLabel'] ?? null) ? $snapshot['classSectionLabel'] : null);
        $menu->setClassSection(is_string($snapshot['classSection'] ?? null) ? $snapshot['classSection'] : null);
        $menu->setClassDivider(is_string($snapshot['classDivider'] ?? null) ? $snapshot['classDivider'] : null);
        $menu->setClassCurrent(is_string($snapshot['classCurrent'] ?? null) ? $snapshot['classCurrent'] : null);
        $menu->setClassBranchExpanded(is_string($snapshot['classBranchExpanded'] ?? null) ? $snapshot['classBranchExpanded'] : null);
        $menu->setClassHasChildren(is_string($snapshot['classHasChildren'] ?? null) ? $snapshot['classHasChildren'] : null);
        $menu->setClassExpanded(is_string($snapshot['classExpanded'] ?? null) ? $snapshot['classExpanded'] : null);
        $menu->setClassCollapsed(is_string($snapshot['classCollapsed'] ?? null) ? $snapshot['classCollapsed'] : null);
        $menu->setPermissionChecker(is_string($snapshot['permissionChecker'] ?? null) ? $snapshot['permissionChecker'] : null);
        $menu->setDepthLimit(is_int($snapshot['depthLimit'] ?? null) ? $snapshot['depthLimit'] : null);
        $menu->setCollapsible(is_bool($snapshot['collapsible'] ?? null) ? $snapshot['collapsible'] : null);
        $menu->setCollapsibleExpanded(is_bool($snapshot['collapsibleExpanded'] ?? null) ? $snapshot['collapsibleExpanded'] : null);
        $menu->setNestedCollapsible(is_bool($snapshot['nestedCollapsible'] ?? null) ? $snapshot['nestedCollapsible'] : null);
        $menu->setNestedCollapsibleSections(is_bool($snapshot['nestedCollapsibleSections'] ?? null) ? $snapshot['nestedCollapsibleSections'] : null);
    }

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
}
