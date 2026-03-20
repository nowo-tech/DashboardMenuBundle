<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Controller\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
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

use function count;
use function in_array;
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
     * @param string|null $stimulusScriptUrl Optional URL of the script that loads Stimulus + Live controller (sets window.Stimulus). When null and Live is enabled, the bundle uses its own asset.
     * @param int $importMaxBytes Maximum allowed size in bytes for JSON import uploads (default 2 MiB)
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

                    return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $menu->getId()]);
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
            'stimulus_script_url'      => $this->stimulusScriptUrl,
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
        $originalCode = $menu->getCode();
        $form         = $this->createForm(MenuType::class, $menu, [
            'action'  => $this->generateUrl(self::ROUTE_MENU_EDIT, ['id' => $id]),
            'section' => $sectionFocus,
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
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->renderImportResponse($request, $form, $isModal);
        }

        return $this->renderImportResponse($request, $form, $isModal);
    }

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
        $appRoutes     = $this->getAppRoutes();
        $locale        = $request->getLocale();
        $redirectToUrl = $this->generateUrl(self::ROUTE_SHOW, ['id' => $id]);
        $parent        = $item->getParent();
        $actionUrl     = $parent !== null && $parent->getId() !== null
            ? $this->generateUrl(self::ROUTE_ITEM_NEW, ['id' => $id, '_query' => ['parent' => $parent->getId()]])
            : $this->generateUrl(self::ROUTE_ITEM_NEW, ['id' => $id]);

        if ($request->query->get('_partial')) {
            if ($this->itemFormLiveComponentEnabled) {
                return $this->render('@NowoDashboardMenuBundle/dashboard/_item_form_live_partial.html.twig', [
                    'menu'              => $menu,
                    'item'              => $item,
                    'app_routes'        => $appRoutes,
                    'exclude_ids'       => [],
                    'locale'            => $locale,
                    'is_edit'           => false,
                    'item_has_children' => false,
                    'action_url'        => $actionUrl,
                    'redirect_to_url'   => $redirectToUrl,
                    'locales'           => $this->locales,
                    'section_focus'     => 'basic',
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
                'csrf_token_id' => 'submit',
                'section'       => 'basic',
            ]);

            return $this->render('@NowoDashboardMenuBundle/dashboard/_item_form_partial.html.twig', [
                'form'              => $form,
                'menu'              => $menu,
                'is_edit'           => false,
                'app_routes'        => $appRoutes,
                'locales'           => $this->locales,
                'item_has_children' => false,
                'section_focus'     => 'basic',
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
            'csrf_token_id' => 'submit',
            'section'       => 'basic',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $item->setMenu($menu);
            if ($item->getItemType() === MenuItem::ITEM_TYPE_SECTION || $item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
                $item->setParent(null);
            }
            if ($item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
                $item->setLabel('');
                $item->setIcon(null);
                $item->setTranslations(null);
            }
            $this->entityManager->persist($item);
            $this->entityManager->flush();
            $this->addFlash('success', 'Item created.');

            return $this->redirectToRefererOr($request, self::ROUTE_SHOW, ['id' => $id]);
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
            'stimulus_script_url'      => $this->stimulusScriptUrl,
            'item_has_children'        => false,
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
        $appRoutes       = $this->getAppRoutesForItem($item, $this->getAppRoutes());
        $locale          = $request->getLocale();
        $excludeIds      = $this->getDescendantIds($item);
        $itemHasChildren = $item->getChildren()->count() > 0;
        $sectionFocus    = in_array($request->query->get('section'), ['basic', 'config'], true) ? $request->query->get('section') : null;
        if ($sectionFocus === null && $request->isMethod('POST')) {
            $sectionFocus = in_array($request->request->get('_section'), ['basic', 'config'], true) ? $request->request->get('_section') : null;
        }

        if ($request->query->get('_partial')) {
            if ($this->itemFormLiveComponentEnabled) {
                return $this->render('@NowoDashboardMenuBundle/dashboard/_item_form_live_partial.html.twig', [
                    'menu'              => $menu,
                    'item'              => $item,
                    'app_routes'        => $appRoutes,
                    'exclude_ids'       => $excludeIds,
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
                'exclude_ids'       => $excludeIds,
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
            'exclude_ids'       => $excludeIds,
            'locale'            => $locale,
            'available_locales' => $this->locales,
            'action'            => $this->generateUrl(self::ROUTE_ITEM_EDIT, ['id' => $id, 'itemId' => $itemId]),
            // Keep CSRF consistent across Symfony versions.
            'csrf_token_id' => 'submit',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($item->getItemType() === MenuItem::ITEM_TYPE_SECTION || $item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
                $item->setParent(null);
            }
            if ($item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
                $item->setLabel('');
                $item->setIcon(null);
                $item->setTranslations(null);
            }
            if ($item->getItemType() === MenuItem::ITEM_TYPE_LINK && $item->getChildren()->count() > 0) {
                $item->setLinkType(null);
                $item->setRouteName(null);
                $item->setRouteParams(null);
                $item->setExternalUrl(null);
            }
            $this->entityManager->flush();
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

            $first = $p[0] ?? '';
            $last  = $p[strlen($p) - 1] ?? '';
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
