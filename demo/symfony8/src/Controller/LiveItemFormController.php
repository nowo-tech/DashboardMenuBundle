<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\View\DashboardPages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Demo: the whole item form (labels, icon, configuration) as the Symfony UX Live component. The dashboard itself uses
 * the component for the configuration section only; hosts may embed it for every section (sectionFocus null), which
 * shows the per-locale label tabs inside a Live component.
 */
final class LiveItemFormController extends AbstractController
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%app.locales%')]
        private readonly array $locales,
    ) {
    }

    #[Route(path: '/demo/live-item-form/{menuId}/{itemId}', name: 'app_demo_live_item_form', requirements: ['menuId' => '\d+', 'itemId' => '\d+'], methods: ['GET'])]
    public function __invoke(int $menuId, int $itemId): Response
    {
        $menu = $this->entityManager->find(Menu::class, $menuId);
        $item = $this->entityManager->find(MenuItem::class, $itemId);
        if (!$menu instanceof Menu || !$item instanceof MenuItem || $item->getMenu() !== $menu) {
            throw new NotFoundHttpException();
        }

        $page = (new DashboardPages($this->urls))->itemFormPage(null, $menu, true, null, $item->getParent(), $item);

        return $this->render('@NowoDashboardMenuBundle/dashboard/item_form.html.twig', [
            'page'                => $page,
            'live'                => true,
            'form'                => null,
            'menu'                => $menu,
            'item'                => $item,
            'app_routes'          => [],
            'exclude_ids'         => [],
            'locale'              => 'en',
            'is_edit'             => true,
            'item_has_children'   => !$item->getChildren()->isEmpty(),
            'action_url'          => $this->urls->generate('nowo_dashboard_menu_dashboard_item_edit', ['id' => $menuId, 'itemId' => $itemId]),
            'redirect_to_url'     => $this->urls->generate('app_demo_live_item_form', ['menuId' => $menuId, 'itemId' => $itemId]),
            'locales'             => $this->locales,
            'section_focus'       => null,
            'stimulus_script_url' => 'bundles/nowodashboardmenu/js/stimulus-live.js',
        ]);
    }
}
