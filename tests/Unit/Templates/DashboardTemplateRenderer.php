<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Templates;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Service\CurrentRouteTreeDecorator;
use Nowo\DashboardMenuBundle\Service\DashboardUiConfig;
use Nowo\DashboardMenuBundle\Service\DefaultMenuCodeResolver;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use ReflectionClass;
use ReflectionProperty;
use SensitiveParameter;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bridge\Twig\Extension\CsrfRuntime;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;

use function dirname;
use function http_build_query;
use function is_array;

/**
 * Real Twig environment for the dashboard templates (UiKit macros, form / translation / routing / asset
 * extensions, MenuExtension with a {@see DashboardUiConfig}). Strict variables, like Symfony in debug.
 */
final readonly class DashboardTemplateRenderer
{
    public const MENU_ID = 7;

    private Environment $twig;

    private FormFactoryInterface $formFactory;

    /**
     * @param array<string, mixed> $ui dashboard.ui config
     * @param array<string, string> $hostTemplates extra templates (name => source), e.g. a host modal shell
     * @param array{enabled?: bool, mode?: string, storage_key?: string} $menuCollapse menu_collapse config (2.6.0)
     */
    public function __construct(array $ui = [], array $hostTemplates = [], string $locale = 'en', ?string $viewsRoot = null, array $menuCollapse = [])
    {
        $root = dirname(__DIR__, 3);

        $filesystem = new FilesystemLoader();
        $filesystem->addPath($viewsRoot ?? $root . '/src/Resources/views', 'NowoDashboardMenuBundle');
        $filesystem->addPath($root . '/vendor/nowo-tech/ui-kit-bundle/src/Resources/views', 'NowoUiKitBundle');
        $filesystem->addPath(dirname((string) (new ReflectionClass(AppVariable::class))->getFileName()) . '/Resources/views/Form');

        $this->twig = new Environment(new ChainLoader([new ArrayLoader($hostTemplates), $filesystem]), [
            'strict_variables' => true,
            'cache'            => false,
            'debug'            => false,
        ]);

        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (glob($root . '/src/Resources/translations/NowoDashboardMenuBundle.*.yaml') ?: [] as $file) {
            $fileLocale = explode('.', basename($file))[1];
            $translator->addResource('yaml', $file, $fileLocale, 'NowoDashboardMenuBundle');
        }
        $this->twig->addExtension(new TranslationExtension($translator));

        $urlGenerator = new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/' . $name . ($parameters !== [] ? '?' . http_build_query($parameters) : '');
            }

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }
        };
        $this->twig->addExtension(new RoutingExtension($urlGenerator));
        $this->twig->addExtension(new AssetExtension(new Packages(new Package(new EmptyVersionStrategy()), [
            'nowo_ui_kit'         => new Package(new EmptyVersionStrategy()),
            'nowo_dashboard_menu' => new Package(new EmptyVersionStrategy()),
        ])));
        $this->twig->addFunction(new TwigFunction('component', static fn (): string => '', ['is_safe' => ['html']]));
        $this->twig->addFunction(new TwigFunction('ux_icon', static fn (string $name): string => '<svg data-icon="' . $name . '"></svg>', ['is_safe' => ['html']]));

        $csrf = new CsrfTokenManager(null, new class extends NativeSessionTokenStorage {
            /** @var array<string, string> */
            private array $tokens = [];

            public function getToken(string $tokenId): string
            {
                return $this->tokens[$tokenId] ??= 'token-' . $tokenId;
            }

            public function setToken(string $tokenId, #[SensitiveParameter] string $token): void
            {
                $this->tokens[$tokenId] = $token;
            }

            public function hasToken(string $tokenId): bool
            {
                return true;
            }

            public function removeToken(string $tokenId): ?string
            {
                return null;
            }
        });
        $this->twig->addExtension(new CsrfExtension());
        $this->twig->addExtension(new FormExtension());
        $twig = $this->twig;
        $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([
            CsrfRuntime::class  => static fn (): CsrfRuntime => new CsrfRuntime($csrf),
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer(new TwigRendererEngine(['form_div_layout.html.twig'], $twig), $csrf),
        ]));

        $requestStack = new RequestStack();
        $request      = Request::create('/admin/menus');
        $request->setLocale($locale);
        $requestStack->push($request);
        $app = new AppVariable();
        $app->setRequestStack($requestStack);
        $app->setDebug(false);
        $this->twig->addGlobal('app', $app);

        $this->twig->addExtension($this->menuExtension(new DashboardUiConfig($ui), $menuCollapse));
        $this->twig->addGlobal('nowo_ui_kit_css_framework', 'bootstrap5');

        $this->formFactory = Forms::createFormFactory();
    }

    /**
     * Compiles a template (syntax / unknown function check) without rendering it.
     */
    public function compile(string $template): void
    {
        $this->twig->load($template);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    /**
     * @param array<string, 'compound'|array<string, class-string<FormTypeInterface<mixed>>|null>|class-string<FormTypeInterface<mixed>>|null> $children
     */
    public function form(string $name, array $children = []): FormView
    {
        $builder = $this->formFactory->createNamedBuilder($name, FormType::class);
        foreach ($children as $child => $type) {
            if ($type === 'compound' || is_array($type)) {
                $sub = $builder->create($child, FormType::class);
                foreach (is_array($type) ? $type : ['code' => null] as $subChild => $subType) {
                    $sub->add($subChild, $subType ?? TextType::class);
                }
                $builder->add($sub);
                continue;
            }
            $builder->add($child, $type ?? TextType::class);
        }

        return $builder->getForm()->createView();
    }

    public function actionForm(string $name): FormView
    {
        return $this->form($name, ['_token' => HiddenType::class]);
    }

    public static function menu(bool $base = false): Menu
    {
        $menu = (new Menu())->setCode('sidebar')->setName('Sidebar')->setBase($base);
        (new ReflectionProperty(Menu::class, 'id'))->setValue($menu, self::MENU_ID);

        return $menu;
    }

    public static function item(int $id, string $label, bool $unanimous, string $type = 'link'): MenuItem
    {
        $item = (new MenuItem())->setLabel($label)->setItemType($type)->setPosition($id * 100)->setIsUnanimous($unanimous);
        (new ReflectionProperty(MenuItem::class, 'id'))->setValue($item, $id);

        return $item;
    }

    /**
     * Context shared by the index page (menus list).
     *
     * @return array<string, mixed>
     */
    public function indexContext(bool $withMenus = true): array
    {
        $menus = $withMenus ? [self::menu()] : [];

        return [
            'menus'                  => $menus,
            'menu_item_counts'       => [self::MENU_ID => 3],
            'search'                 => '',
            'search_form'            => $this->form('search', ['q' => null]),
            'menu_delete_tokens'     => [self::MENU_ID => 'tok-del'],
            'delete_menu_modal_form' => $this->actionForm('delete_menu_modal'),
            'pagination'             => ['current_page' => 1, 'per_page' => 1, 'total' => 2, 'total_pages' => 2],
            'new_menu_form'          => $this->form('menu', ['definition' => 'compound']),
            'modal_classes'          => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showContext(): array
    {
        $first  = self::item(1, 'Home', true);
        $second = self::item(2, 'Reports', false);

        return [
            'menu'                     => self::menu(),
            'items'                    => [$first, $second],
            'item_depths'              => [1 => 0, 2 => 0],
            'item_parent_labels'       => [],
            'prev_sibling'             => [1 => null, 2 => 1],
            'next_sibling'             => [1 => 2, 2 => null],
            'item_move_up_forms'       => [2 => $this->actionForm('move_up_2')],
            'item_move_down_forms'     => [1 => $this->actionForm('move_down_1')],
            'item_delete_tokens'       => [1 => 't1', 2 => 't2'],
            'reindex_positions_form'   => $this->actionForm('reindex'),
            'check_parent_cycles_form' => $this->actionForm('cycles'),
            'position_step'            => 100,
            'delete_item_modal_form'   => $this->actionForm('delete_item_modal'),
            'modal_classes'            => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reorderContext(): array
    {
        $item = self::item(1, 'Home', true);

        return [
            'menu'          => self::menu(),
            'items_tree'    => [['item' => $item, 'children' => []]],
            'reorder_form'  => $this->form('reorder', ['tree' => HiddenType::class]),
            'modal_classes' => [],
        ];
    }

    /**
     * @param array{enabled?: bool, mode?: string, storage_key?: string} $menuCollapse
     */
    private function menuExtension(DashboardUiConfig $ui, array $menuCollapse = []): MenuExtension
    {
        $menuRepo  = (new ReflectionClass(MenuRepository::class))->newInstanceWithoutConstructor();
        $itemRepo  = (new ReflectionClass(MenuItemRepository::class))->newInstanceWithoutConstructor();
        $config    = new MenuConfigResolver(['project' => null], $menuRepo);
        $container = new Container();

        $router = new class implements RouterInterface {
            public function getRouteCollection(): RouteCollection
            {
                return new RouteCollection();
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '#';
            }

            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            /**
             * @return array<string, mixed>
             */
            public function match(string $pathinfo): array
            {
                return [];
            }
        };
        $requestStack = new RequestStack();
        $urlResolver  = new MenuUrlResolver($router, $requestStack, $router, $container);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader($menuRepo, $itemRepo, $config, $iconResolver, $container, new AllowAllMenuPermissionChecker(), $container, [], null, null, 60);

        return new MenuExtension(
            $loader,
            $urlResolver,
            $config,
            new DefaultMenuCodeResolver(),
            $requestStack,
            new CurrentRouteTreeDecorator($urlResolver),
            new MenuLocaleResolver([]),
            $iconResolver,
            '@NowoDashboardMenuBundle/dashboard/layout.html.twig',
            false,
            null,
            null,
            null,
            false,
            'd-flex align-items-center flex-nowrap',
            '1em',
            'bootstrap5',
            'bootstrap-icons',
            $ui,
            true,
            $menuCollapse,
        );
    }
}
