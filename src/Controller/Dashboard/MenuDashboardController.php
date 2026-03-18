<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Controller\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\CopyMenuType;
use Nowo\DashboardMenuBundle\Form\ImportMenuType;
use Nowo\DashboardMenuBundle\Form\MenuItemType;
use Nowo\DashboardMenuBundle\Form\MenuType;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuExporter;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function count;
use function is_array;
use function is_string;
use function json_encode;

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
    public const ROUTE_INDEX          = 'nowo_dashboard_menu_dashboard_index';
    public const ROUTE_SHOW           = 'nowo_dashboard_menu_dashboard_show';
    public const ROUTE_MENU_NEW       = 'nowo_dashboard_menu_dashboard_menu_new';
    public const ROUTE_MENU_EDIT      = 'nowo_dashboard_menu_dashboard_menu_edit';
    public const ROUTE_MENU_DELETE    = 'nowo_dashboard_menu_dashboard_menu_delete';
    public const ROUTE_MENU_COPY      = 'nowo_dashboard_menu_dashboard_menu_copy';
    public const ROUTE_ITEM_NEW       = 'nowo_dashboard_menu_dashboard_item_new';
    public const ROUTE_ITEM_EDIT      = 'nowo_dashboard_menu_dashboard_item_edit';
    public const ROUTE_ITEM_DELETE    = 'nowo_dashboard_menu_dashboard_item_delete';
    public const ROUTE_ITEM_MOVE_UP   = 'nowo_dashboard_menu_dashboard_item_move_up';
    public const ROUTE_ITEM_MOVE_DOWN = 'nowo_dashboard_menu_dashboard_item_move_down';
    public const ROUTE_EXPORT_MENU    = 'nowo_dashboard_menu_dashboard_export_menu';
    public const ROUTE_EXPORT_ALL     = 'nowo_dashboard_menu_dashboard_export_all';
    public const ROUTE_IMPORT         = 'nowo_dashboard_menu_dashboard_import';

    /**
     * @return array<string, string>
     */
    private function getDashboardRoutes(): array
    {
        return [
            'index'          => self::ROUTE_INDEX,
            'show'           => self::ROUTE_SHOW,
            'menu_new'       => self::ROUTE_MENU_NEW,
            'menu_edit'      => self::ROUTE_MENU_EDIT,
            'menu_delete'    => self::ROUTE_MENU_DELETE,
            'menu_copy'      => self::ROUTE_MENU_COPY,
            'item_new'       => self::ROUTE_ITEM_NEW,
            'item_edit'      => self::ROUTE_ITEM_EDIT,
            'item_delete'    => self::ROUTE_ITEM_DELETE,
            'item_move_up'   => self::ROUTE_ITEM_MOVE_UP,
            'item_move_down' => self::ROUTE_ITEM_MOVE_DOWN,
            'export_menu'    => self::ROUTE_EXPORT_MENU,
            'export_all'     => self::ROUTE_EXPORT_ALL,
            'import'         => self::ROUTE_IMPORT,
        ];
    }

    /**
     * @param list<string> $routeNameExcludePatterns Regex patterns to exclude route names from the selector
     * @param list<string> $locales Enabled locales for item labels configured in the bundle
     * @param array<string, string> $modalSizes Modal size per type: menu_form, copy, item_form, delete (values: normal, lg, xl)
     * @param string|null $iconSelectorScriptUrl Optional URL of the icon-selector script (Stimulus/UX) for the item form modal
     */
    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly MenuItemRepository $menuItemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly MenuExporter $menuExporter,
        private readonly MenuImporter $menuImporter,
        private readonly array $routeNameExcludePatterns = [],
        private readonly array $locales = [],
        private readonly bool $paginationEnabled = true,
        private readonly int $paginationPerPage = 20,
        private readonly array $modalSizes = [],
        private readonly ?string $iconSelectorScriptUrl = null,
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

        $newMenu     = new Menu();
        $newMenuForm = $this->createForm(MenuType::class, $newMenu, [
            'action' => $this->generateUrl(self::ROUTE_MENU_NEW),
        ]);

        return $this->render('@NowoDashboardMenuBundle/dashboard/index.html.twig', [
            'menus'                    => $menus,
            'search'                   => $search,
            'pagination'               => $pagination,
            'new_menu_form'            => $newMenuForm,
            'dashboard_show_route'     => self::ROUTE_SHOW,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'modal_classes'            => $this->getModalClasses(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
        ]);
    }

    #[Route(path: '/menu/new', name: 'menu_new', methods: ['GET', 'POST'])]
    public function newMenu(Request $request): Response
    {
        $menu = new Menu();
        $form = $this->createForm(MenuType::class, $menu, [
            'action' => $this->generateUrl(self::ROUTE_MENU_NEW),
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

                    return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $menu->getId()]);
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
        ]);
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

    #[Route(path: '/{id}/item/{itemId}/move-up', name: 'item_move_up', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['GET'])]
    public function itemMoveUp(int $id, int $itemId): \Symfony\Component\HttpFoundation\RedirectResponse
    {
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

        return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id, '_fragment' => 'item-' . $itemId]);
    }

    #[Route(path: '/{id}/item/{itemId}/move-down', name: 'item_move_down', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['GET'])]
    public function itemMoveDown(int $id, int $itemId): \Symfony\Component\HttpFoundation\RedirectResponse
    {
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

        return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id, '_fragment' => 'item-' . $itemId]);
    }

    #[Route(path: '/{id}/edit', name: 'menu_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editMenu(Request $request, int $id): Response
    {
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $originalCode = $menu->getCode();
        $form         = $this->createForm(MenuType::class, $menu, [
            'action' => $this->generateUrl(self::ROUTE_MENU_EDIT, ['id' => $id]),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($menu->isBase()) {
                $menu->setCode($originalCode);
            }
            $existing = $this->menuRepository->findOneByCodeAndContext($menu->getCode(), $menu->getContext());
            if ($existing instanceof Menu && $existing->getId() !== $menu->getId()) {
                $form->addError(new FormError($this->translator->trans('dashboard.unique_code_context_error', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN)));
            } else {
                $this->entityManager->flush();
                $this->addFlash('success', 'Menu updated.');

                return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id]);
            }
        }

        if ($request->query->get('_partial')) {
            return $this->render('@NowoDashboardMenuBundle/dashboard/_menu_form_partial.html.twig', [
                'form'             => $form,
                'is_edit'          => true,
                'dashboard_routes' => $this->getDashboardRoutes(),
            ]);
        }

        return $this->render('@NowoDashboardMenuBundle/dashboard/menu_form.html.twig', [
            'form'                     => $form,
            'is_edit'                  => true,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
        ]);
    }

    #[Route(path: '/{id}/delete', name: 'menu_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteMenu(int $id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $menu = $this->menuRepository->findOneById($id);
        if (!$menu instanceof Menu) {
            throw $this->createNotFoundException('Menu not found.');
        }
        $em = $this->entityManager;
        $em->remove($menu);
        $em->flush();
        $this->addFlash('success', 'Menu deleted.');

        return $this->redirectToRoute(self::ROUTE_INDEX);
    }

    #[Route(path: '/export', name: 'export_all', methods: ['GET'])]
    public function exportAll(Request $request): StreamedResponse
    {
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
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $file = $data['file'] ?? null;
            if ($file instanceof UploadedFile) {
                $content = $file->getContent();
                $decoded = json_decode($content, true);
                if (!is_array($decoded)) {
                    $this->addFlash('error', $this->translator->trans('dashboard.import_json_invalid', [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN));
                } else {
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

                    return $this->redirectToRoute(self::ROUTE_INDEX);
                }
            }
        }

        return $this->render('@NowoDashboardMenuBundle/dashboard/import.html.twig', [
            'form'             => $form,
            'dashboard_routes' => $this->getDashboardRoutes(),
        ]);
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
                ]);
            }
            $copy = $this->cloneMenuWithItems($menu, $newCode, $newName);
            $this->addFlash('success', 'Menu copied successfully.');

            return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $copy->getId()]);
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
        $copy->setClassItem($source->getClassItem());
        $copy->setClassLink($source->getClassLink());
        $copy->setClassChildren($source->getClassChildren());
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

        $items     = $this->menuItemRepository->findAllForMenuOrderedByTree($source, 'en');
        $rootItems = [];
        foreach ($items as $item) {
            if ($item->getParent() === null) {
                $rootItems[] = $item;
            }
        }
        foreach ($rootItems as $rootItem) {
            $this->cloneItemRecursive($rootItem, $copy, null);
        }
        $em->flush();

        return $copy;
    }

    private function cloneItemRecursive(MenuItem $source, Menu $newMenu, ?MenuItem $newParent): void
    {
        $em   = $this->entityManager;
        $copy = new MenuItem();
        $copy->setMenu($newMenu);
        $copy->setParent($newParent);
        $copy->setPosition($source->getPosition());
        $copy->setLabel($source->getLabel());
        $copy->setTranslations($source->getTranslations());
        $copy->setItemType($source->getItemType());
        $copy->setLinkType($source->getLinkType());
        $copy->setRouteName($source->getRouteName());
        $copy->setRouteParams($source->getRouteParams());
        $copy->setExternalUrl($source->getExternalUrl());
        $copy->setIcon($source->getIcon());
        $copy->setPermissionKey($source->getPermissionKey());
        $copy->setTargetBlank($source->getTargetBlank());
        $em->persist($copy);
        $em->flush();

        foreach ($source->getChildren() as $child) {
            $this->cloneItemRecursive($child, $newMenu, $copy);
        }
    }

    #[Route(path: '/{id}/item/new', name: 'item_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
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
        $form      = $this->createForm(MenuItemType::class, $item, [
            'app_routes'  => $appRoutes,
            'menu'        => $menu,
            'exclude_ids' => [],
            'locale'      => $locale,
            'action'      => $this->generateUrl(self::ROUTE_ITEM_NEW, ['id' => $id]),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $item->setMenu($menu);
            $this->entityManager->persist($item);
            $this->entityManager->flush();
            $this->addFlash('success', 'Item created.');

            return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id]);
        }

        if ($request->query->get('_partial')) {
            return $this->render('@NowoDashboardMenuBundle/dashboard/_item_form_partial.html.twig', [
                'form'       => $form,
                'menu'       => $menu,
                'is_edit'    => false,
                'app_routes' => $appRoutes,
                'locales'    => $this->locales,
            ]);
        }

        return $this->render('@NowoDashboardMenuBundle/dashboard/item_form.html.twig', [
            'menu'                     => $menu,
            'form'                     => $form,
            'is_edit'                  => false,
            'app_routes'               => $appRoutes,
            'locales'                  => $this->locales,
            'dashboard_show_route'     => self::ROUTE_SHOW,
            'dashboard_routes'         => $this->getDashboardRoutes(),
            'icon_selector_script_url' => $this->iconSelectorScriptUrl,
        ]);
    }

    #[Route(path: '/{id}/item/{itemId}/edit', name: 'item_edit', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['GET', 'POST'])]
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
        $appRoutes  = $this->getAppRoutesForItem($item, $this->getAppRoutes());
        $locale     = $request->getLocale();
        $excludeIds = $this->getDescendantIds($item);
        $form       = $this->createForm(MenuItemType::class, $item, [
            'app_routes'  => $appRoutes,
            'menu'        => $menu,
            'exclude_ids' => $excludeIds,
            'locale'      => $locale,
            'action'      => $this->generateUrl(self::ROUTE_ITEM_EDIT, ['id' => $id, 'itemId' => $itemId]),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Item updated.');

            return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id]);
        }

        if ($request->query->get('_partial')) {
            return $this->render('@NowoDashboardMenuBundle/dashboard/_item_form_partial.html.twig', [
                'form'       => $form,
                'menu'       => $menu,
                'is_edit'    => true,
                'app_routes' => $appRoutes,
                'locales'    => $this->locales,
            ]);
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
        ]);
    }

    #[Route(path: '/{id}/item/{itemId}/delete', name: 'item_delete', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['POST'])]
    public function deleteItem(int $id, int $itemId): \Symfony\Component\HttpFoundation\RedirectResponse
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
        $em = $this->entityManager;
        $em->remove($item);
        $em->flush();
        $this->addFlash('success', 'Item deleted.');

        return $this->redirectToRoute(self::ROUTE_SHOW, ['id' => $id]);
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
            if (@preg_match($pattern, $routeName) === 1) {
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
     * @return list<int>
     */
    private function getDescendantIds(MenuItem $item): array
    {
        $ids = [];
        $id  = $item->getId();
        if ($id !== null) {
            $ids[] = $id;
        }
        foreach ($item->getChildren() as $child) {
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }

        return $ids;
    }
}
