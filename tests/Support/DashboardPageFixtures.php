<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Support;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\CopyMenuType;
use Nowo\DashboardMenuBundle\Form\DashboardActionType;
use Nowo\DashboardMenuBundle\Form\ImportMenuType;
use Nowo\DashboardMenuBundle\Form\MenuConfigType;
use Nowo\DashboardMenuBundle\Form\MenuDefinitionType;
use Nowo\DashboardMenuBundle\Form\MenuItemBasicType;
use Nowo\DashboardMenuBundle\Form\MenuItemConfigType;
use Nowo\DashboardMenuBundle\Form\MenuItemIconType;
use Nowo\DashboardMenuBundle\Form\MenuItemType;
use Nowo\DashboardMenuBundle\Form\MenuType;
use Nowo\DashboardMenuBundle\Form\SearchQueryType;
use Nowo\DashboardMenuBundle\Form\SortableTreeType;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Service\DashboardUiConfig;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\View\DashboardPages;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;

use function dirname;

/**
 * Realistic view models and Twig contexts of every dashboard page for the page contract and render tests: menus
 * (sidebar with items, a base footer menu), a real form factory (FormKit-backed types with a class-free profile,
 * CSRF) and a URL generator with the dashboard routes under /admin/menus.
 */
final class DashboardPageFixtures
{
    public const PREFIX = '/admin/menus';

    /** Dashboard routes (name suffix => path), mirroring MenuDashboardController. */
    private const ROUTES = [
        'index'                     => '',
        'menu_new'                  => '/menu/new',
        'show_items_reorder'        => '/{id}/items/reorder',
        'show'                      => '/{id}',
        'items_reindex_positions'   => '/{id}/items/reindex-positions',
        'items_check_parent_cycles' => '/{id}/items/check-parent-cycles',
        'items_reorder_tree'        => '/{id}/items/reorder-tree',
        'item_move_up'              => '/{id}/item/{itemId}/move-up',
        'item_move_down'            => '/{id}/item/{itemId}/move-down',
        'menu_edit'                 => '/{id}/edit',
        'menu_delete'               => '/{id}/delete',
        'export_all'                => '/export',
        'export_menu'               => '/{id}/export',
        'import'                    => '/import',
        'menu_copy'                 => '/{id}/copy',
        'item_new'                  => '/{id}/item/new',
        'item_edit'                 => '/{id}/item/{itemId}/edit',
        'item_delete'               => '/{id}/item/{itemId}/delete',
    ];

    public static function urls(): UrlGenerator
    {
        $routes = new RouteCollection();
        foreach (self::ROUTES as $name => $path) {
            $routes->add('nowo_dashboard_menu_dashboard_' . $name, new Route(self::PREFIX . $path));
        }

        return new UrlGenerator($routes, new RequestContext());
    }

    public static function pages(?DashboardUiConfig $legacyUi = null): DashboardPages
    {
        return new DashboardPages(self::urls(), $legacyUi ?? new DashboardUiConfig(), ['menu_form' => 'lg', 'item_form' => 'lg']);
    }

    public static function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 2) . '/src/Resources/translations/NowoDashboardMenuBundle.en.yaml', 'en', 'NowoDashboardMenuBundle');

        return $translator;
    }

    public static function formFactory(): FormFactoryInterface
    {
        $translator = self::translator();
        // Mirrors the dashboard_menu FormKit profile without framework classes (the host theme owns them).
        $merger = new FormOptionsMerger(
            [
                'dashboard_menu' => [
                    'translation_domain'              => 'NowoDashboardMenuBundle',
                    'convention_requires_translation' => true,
                    'defaults'                        => ['attr' => [], 'row_attr' => []],
                    'field_types'                     => [],
                ],
            ],
            'dashboard_menu',
            new ConstraintDefinitionFactory(),
            translator: $translator,
        );

        /** @var MenuItemRepository $repository never queried: the fixtures build item forms without the parent field */
        $repository = (new ReflectionClass(MenuItemRepository::class))->newInstanceWithoutConstructor();
        $types      = [
            new SearchQueryType($translator),
            new CopyMenuType(),
            new ImportMenuType($translator),
            new MenuDefinitionType(),
            new MenuConfigType([], [], []),
            new MenuItemBasicType(['en', 'es']),
            new MenuItemIconType(new MenuIconNameResolver()),
            new MenuItemConfigType($repository, ['authenticated', 'admin'], [], 'en', $translator),
        ];
        $builder = (new FormFactoryBuilder())
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new CsrfExtension(self::csrfTokenManager()))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addTypeExtension(new class extends AbstractTypeExtension {
                // Options of symfony/ux-autocomplete's ChoiceType extension and of an icon selector type (the item form
                // asks for them when those packages are installed).
                public static function getExtendedTypes(): iterable
                {
                    return [FormType::class];
                }

                public function configureOptions(OptionsResolver $resolver): void
                {
                    $resolver->setDefined(['autocomplete', 'tom_select_options', 'autocomplete_url', 'options_as_html', 'max_results', 'min_characters', 'preload', 'no_results_found_text', 'no_more_results_text', 'extra_options', 'mode']);
                }
            })
            ->addType(new MenuType())
            ->addType(new MenuItemType())
            ->addType(new DashboardActionType())
            ->addType(new SortableTreeType());
        foreach ($types as $type) {
            $type->setFormOptionsMerger($merger);
            $builder->addType($type);
        }

        return $builder->getFormFactory();
    }

    public static function sidebar(): Menu
    {
        $menu = (new Menu())->setCode('sidebar')->setName('Main sidebar');
        $menu->setContext(['partnerId' => 1]);
        $menu->setPermissionChecker('App\\Security\\MenuPermissionChecker');
        $menu->setDepthLimit(3);
        $menu->setCollapsible(true);
        $menu->setNestedCollapsible(false);
        $menu->setClassMenu('nav flex-column');
        $menu->setClassLink('nav-link');

        return self::withId($menu, 1);
    }

    public static function footer(): Menu
    {
        $menu = (new Menu())->setCode('footer');
        $menu->setBase(true);

        return self::withId($menu, 2);
    }

    /**
     * @return list<MenuItem> sidebar items in tree display order (section, two links, a child, a divider)
     */
    public static function items(): array
    {
        $menu    = self::sidebar();
        $section = self::withId((new MenuItem())->setMenu($menu)->setLabel('Content')->setItemType(MenuItem::ITEM_TYPE_SECTION)->setPosition(0), 10);
        $home    = self::withId((new MenuItem())->setMenu($menu)->setLabel('Home')->setLinkType(MenuItem::LINK_TYPE_ROUTE)->setRouteName('app_home')->setPosition(1), 11);
        $home->setTranslations(['es' => 'Inicio']);
        $home->setPermissionKeys(['authenticated']);
        $blog = self::withId((new MenuItem())->setMenu($menu)->setLabel('Blog')->setLinkType(MenuItem::LINK_TYPE_EXTERNAL)->setExternalUrl('https://blog.example.test')->setPosition(2), 12);
        $blog->setIsUnanimous(false);
        $post    = self::withId((new MenuItem())->setMenu($menu)->setLabel('Latest post')->setParent($blog)->setLinkType(MenuItem::LINK_TYPE_ROUTE)->setRouteName('app_post')->setRouteParams(['slug' => 'hello'])->setPosition(0), 13);
        $divider = self::withId((new MenuItem())->setMenu($menu)->setLabel('')->setItemType(MenuItem::ITEM_TYPE_DIVIDER)->setPosition(3), 14);

        return [$section, $home, $blog, $post, $divider];
    }

    /**
     * @return array<string, mixed>
     */
    public static function index(bool $empty = false, string $search = ''): array
    {
        $menus = $empty ? [] : [self::sidebar(), self::footer()];
        $pages = self::pages();

        return [
            'page' => $pages->indexPage(
                $menus,
                [1 => 5, 2 => 0],
                self::formFactory()->create(SearchQueryType::class, ['q' => $search], ['action' => self::PREFIX])->createView(),
                $search,
                $empty ? null : $pages->pagination(1, 20, 2, 1),
                $empty ? [] : [1 => self::actionForm('/admin/menus/1/delete', 'delete_menu_1')],
            ),
            'dialogs' => $pages->dialogs([DashboardPages::DIALOG_MENU, DashboardPages::DIALOG_COPY, DashboardPages::DIALOG_IMPORT]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function show(bool $empty = false): array
    {
        $menu  = self::sidebar();
        $items = $empty ? [] : self::items();
        $pages = self::pages();
        $up    = [];
        $down  = [];
        $del   = [];
        foreach ([11 => [10, 12], 12 => [11, 14], 14 => [12, null], 10 => [null, 11], 13 => [null, null]] as $id => [$prev, $next]) {
            if ($prev !== null) {
                $up[$id] = self::actionForm('/admin/menus/1/item/' . $id . '/move-up', 'item_move_up_' . $id);
            }
            if ($next !== null) {
                $down[$id] = self::actionForm('/admin/menus/1/item/' . $id . '/move-down', 'item_move_down_' . $id);
            }
            $del[$id] = self::actionForm('/admin/menus/1/item/' . $id . '/delete', 'delete_item_' . $id);
        }

        return [
            'page'           => $pages->showPage($menu, $items !== [], self::actionForm('/admin/menus/1/items/reindex-positions', 'reindex_positions_1'), self::actionForm('/admin/menus/1/items/check-parent-cycles', 'check_parent_cycles_1'), 100),
            'menu'           => $menu,
            'item_columns'   => $pages->itemColumns('en'),
            'item_rows'      => $pages->itemRows($menu, $items, [10 => 0, 11 => 0, 12 => 0, 13 => 1, 14 => 0], [10 => '— Root —', 11 => '— Root —', 12 => '— Root —', 13 => 'Blog', 14 => '— Root —'], $up, $down, $del, 'en'),
            'add_item'       => $pages->addItemAction($menu),
            'current_locale' => 'en',
            'dialogs'        => $pages->dialogs([DashboardPages::DIALOG_MENU, DashboardPages::DIALOG_COPY, DashboardPages::DIALOG_ITEM]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reorder(bool $empty = false): array
    {
        $menu  = self::sidebar();
        $items = self::items();
        $tree  = $empty ? [] : [
            ['item' => $items[0], 'children' => []],
            ['item' => $items[1], 'children' => []],
            ['item' => $items[2], 'children' => [['item' => $items[3], 'children' => []]]],
            ['item' => $items[4], 'children' => []],
        ];

        return [
            'header'       => self::pages()->reorderHeader($menu),
            'layout'       => null,
            'bundle'       => DashboardPages::BUNDLE,
            'menu'         => $menu,
            'items_tree'   => $tree,
            'reorder_form' => self::formFactory()->create(SortableTreeType::class, null, ['action' => '/admin/menus/1/items/reorder-tree', 'csrf_token_id' => 'reorder_tree_1'])->createView(),
            'dialogs'      => self::pages()->dialogs([DashboardPages::DIALOG_MENU, DashboardPages::DIALOG_COPY, DashboardPages::DIALOG_ITEM]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function menuForm(bool $isEdit, ?string $section = null): array
    {
        $menu = $isEdit ? self::sidebar() : new Menu();
        $form = self::formFactory()->create(MenuType::class, $menu, ['action' => $isEdit ? '/admin/menus/1/edit' : '/admin/menus/menu/new', 'section' => $isEdit ? $section : 'basic'])->createView();

        return [
            'form'          => $form,
            'is_edit'       => $isEdit,
            'section_focus' => $section,
            'menu'          => $isEdit ? $menu : null,
            'page'          => self::pages()->menuFormPage($form, $isEdit, $isEdit ? $menu : null, $section),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemForm(bool $isEdit, ?string $section = null, bool $withError = false): array
    {
        $menu  = self::sidebar();
        $items = self::items();
        $item  = $isEdit ? $items[1] : (new MenuItem())->setMenu($menu);
        $form  = self::formFactory()->create(MenuItemType::class, $item, [
            'available_locales'    => ['en', 'es'],
            'action'               => $isEdit ? '/admin/menus/1/item/11/edit' : '/admin/menus/1/item/new',
            'csrf_token_id'        => 'submit',
            'section'              => $isEdit ? $section : 'minimal',
            'include_translations' => $isEdit,
        ]);
        if ($withError) {
            $form->get('basic')->get('label')->addError(new \Symfony\Component\Form\FormError('This value should not be blank.'));
        }
        $view = $form->createView();

        return [
            'form'              => $view,
            'menu'              => $menu,
            'is_edit'           => $isEdit,
            'app_routes'        => ['app_home' => ['label' => 'app_home (/)', 'params' => []]],
            'locales'           => ['en', 'es'],
            'item_has_children' => false,
            'section_focus'     => $isEdit ? $section : 'minimal',
            'show_translations' => $isEdit,
            'page'              => self::pages()->itemFormPage($view, $menu, $isEdit, $isEdit ? $section : 'minimal', null, $isEdit ? $item : null),
        ];
    }

    /**
     * Full item page with the Live component (configuration section): UiKit 2.2 body-only form page, the component
     * renders its own form.
     *
     * @return array<string, mixed>
     */
    public static function itemFormLive(): array
    {
        $menu = self::sidebar();
        $item = self::items()[1];

        return [
            'live'              => true,
            'form'              => null,
            'menu'              => $menu,
            'item'              => $item,
            'app_routes'        => [],
            'exclude_ids'       => [],
            'locale'            => 'en',
            'is_edit'           => true,
            'item_has_children' => false,
            'action_url'        => '/admin/menus/1/item/11/edit',
            'redirect_to_url'   => '/admin/menus/1',
            'locales'           => ['en', 'es'],
            'section_focus'     => 'config',
            'page'              => self::pages()->itemFormPage(null, $menu, true, 'config', null, $item),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function copyForm(): array
    {
        $menu = self::sidebar();
        $form = self::formFactory()->create(CopyMenuType::class, ['code' => 'sidebar_copy', 'name' => 'Main sidebar (copy)'], ['action' => '/admin/menus/1/copy'])->createView();

        return ['menu' => $menu, 'form' => $form, 'page' => self::pages()->copyFormPage($form, $menu)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function importForm(): array
    {
        $form = self::formFactory()->create(ImportMenuType::class, null, ['action' => '/admin/menus/import'])->createView();

        return ['form' => $form, 'page' => self::pages()->importFormPage($form)];
    }

    public static function actionForm(string $action, string $tokenId): FormView
    {
        return self::formFactory()->create(DashboardActionType::class, null, ['action' => $action, 'csrf_token_id' => $tokenId])->createView();
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    public static function withId(object $entity, int $id): object
    {
        (new ReflectionProperty($entity, 'id'))->setValue($entity, $id);

        return $entity;
    }

    private static function csrfTokenManager(): CsrfTokenManagerInterface
    {
        return new class implements CsrfTokenManagerInterface {
            public function getToken(string $tokenId): CsrfToken
            {
                return new CsrfToken($tokenId, 'token-' . $tokenId);
            }

            public function refreshToken(string $tokenId): CsrfToken
            {
                return $this->getToken($tokenId);
            }

            public function removeToken(string $tokenId): ?string
            {
                return null;
            }

            public function isTokenValid(CsrfToken $token): bool
            {
                return true;
            }
        };
    }
}
