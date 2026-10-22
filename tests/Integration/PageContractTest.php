<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration;

use Nowo\DashboardMenuBundle\Form\MenuItemType;
use Nowo\DashboardMenuBundle\Tests\Support\DashboardPageFixtures;
use Nowo\FormKitBundle\Twig\LocaleTabsTwigExtension;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\DataCollectorTranslator;
use Twig\Environment;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

use function array_filter;
use function array_map;
use function array_values;
use function assert;
use function dirname;
use function htmlspecialchars;
use function json_encode;
use function strpos;
use function substr_count;

/**
 * REQ-UI-003: every dashboard page extends a UiKit scaffold (or is the declared unique reorder page), fills the
 * canonical blocks, ships no chrome of its own (no <html>, CDN, inline handlers, framework classes outside the theme
 * slots) and renders translated labels only (UiKit PageContractTestCase).
 */
final class PageContractTest extends PageContractTestCase
{
    private const VIEWS = '@NowoDashboardMenuBundle/dashboard/';

    public static function pageFixtures(): iterable
    {
        yield 'menus list' => new PageFixture(self::VIEWS . 'index.html.twig', DashboardPageFixtures::index());
        yield 'menus list (search)' => new PageFixture(self::VIEWS . 'index.html.twig', DashboardPageFixtures::index(false, 'side'));
        yield 'menus list (empty)' => new PageFixture(self::VIEWS . 'index.html.twig', DashboardPageFixtures::index(true));
        yield 'menus list (intro)' => new PageFixture(self::VIEWS . 'index.html.twig', DashboardPageFixtures::index(false, '', 'dashboard.menus_intro'));
        yield 'menu detail' => new PageFixture(self::VIEWS . 'show.html.twig', DashboardPageFixtures::show());
        yield 'menu detail (no items)' => new PageFixture(self::VIEWS . 'show.html.twig', DashboardPageFixtures::show(true));
        yield 'drag reorder' => new PageFixture(self::VIEWS . 'show_items_reorder.html.twig', DashboardPageFixtures::reorder());
        yield 'drag reorder (no items)' => new PageFixture(self::VIEWS . 'show_items_reorder.html.twig', DashboardPageFixtures::reorder(true));
        yield 'new menu' => new PageFixture(self::VIEWS . 'menu_form.html.twig', DashboardPageFixtures::menuForm(false));
        yield 'edit menu' => new PageFixture(self::VIEWS . 'menu_form.html.twig', DashboardPageFixtures::menuForm(true));
        yield 'edit menu configuration' => new PageFixture(self::VIEWS . 'menu_form.html.twig', DashboardPageFixtures::menuForm(true, 'config'));
        yield 'new item' => new PageFixture(self::VIEWS . 'item_form.html.twig', DashboardPageFixtures::itemForm(false));
        yield 'new item (error)' => new PageFixture(self::VIEWS . 'item_form.html.twig', DashboardPageFixtures::itemForm(false, null, true));
        yield 'edit item' => new PageFixture(self::VIEWS . 'item_form.html.twig', DashboardPageFixtures::itemForm(true));
        yield 'edit item configuration (Live component)' => new PageFixture(self::VIEWS . 'item_form.html.twig', DashboardPageFixtures::itemFormLive());
        yield 'copy menu' => new PageFixture(self::VIEWS . 'copy_menu.html.twig', DashboardPageFixtures::copyForm());
        yield 'import' => new PageFixture(self::VIEWS . 'import.html.twig', DashboardPageFixtures::importForm());
    }

    /**
     * @return iterable<string, array{PageFixture}>
     */
    public static function translationPages(): iterable
    {
        foreach (self::pageFixtures() as $name => $fixture) {
            yield $name => [$fixture];
        }
    }

    /**
     * The form theme translates labels, help, placeholders and choice labels in the field domain: placeholders are
     * catalogue keys translated once (they were translated by the form types and looked up again), literal examples
     * are catalogue entries and literal choices are not looked up. Every lookup of a page hits the catalogues.
     */
    #[DataProvider('translationPages')]
    public function testPageLooksUpNoMissingMessage(PageFixture $fixture): void
    {
        $twig      = $this->createTwig();
        $collector = $twig->getExtension(TranslationExtension::class)->getTranslator();
        assert($collector instanceof DataCollectorTranslator);
        $twig->render($fixture->template, $fixture->context);

        $missing = array_filter(
            $collector->getCollectedMessages(),
            static fn (array $message): bool => $message['state'] === DataCollectorTranslator::MESSAGE_MISSING,
        );
        self::assertSame([], array_values(array_map(static fn (array $message): string => $message['domain'] . ': ' . $message['id'], $missing)));
    }

    public function testMenusListRendersCellsRowActionsAndDialogs(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'index.html.twig', DashboardPageFixtures::index()))->html;

        self::assertStringContainsString('href="/admin/menus/1"', $html);
        self::assertStringContainsString('<code class="', $html);
        self::assertStringContainsString('>sidebar</code>', $html);
        self::assertStringContainsString('Main sidebar', $html);
        self::assertStringContainsString('partnerId', $html);
        self::assertStringContainsString('nowo-dashboard-menu-badge--base', $html, 'The base footer menu is flagged.');
        // Dialog openers: plain links to the form pages (progressive enhancement).
        self::assertStringContainsString('href="/admin/menus/1/edit?section=config"', $html);
        self::assertStringContainsString('data-nowo-dashboard-menu-dialog="nowo-dashboard-menu-dialog-menu"', $html);
        self::assertStringContainsString('href="/admin/menus/menu/new"', $html);
        self::assertStringContainsString('data-nowo-dashboard-menu-dialog="nowo-dashboard-menu-dialog-import"', $html);
        self::assertMatchesRegularExpression('/<dialog id="nowo-dashboard-menu-dialog-menu" class="[^"]*nowo-ui-dialog--lg"/', $html);
        self::assertSame(3, substr_count($html, 'data-nowo-dashboard-menu-dialog-root='));
        // Delete only for the non-base menu, behind the UiKit confirm dialog with the menu name.
        self::assertStringContainsString('action="/admin/menus/1/delete"', $html);
        self::assertStringNotContainsString('/admin/menus/2/delete', $html);
        self::assertStringContainsString('Delete menu &quot;sidebar — Main sidebar&quot; and all its items?', $html);
        self::assertStringContainsString('value="token-delete_menu_1"', $html);
        self::assertStringContainsString('id="nowo-dashboard-menu-config"', $html);
        self::assertStringContainsString('"dashboardBase":"/admin/menus"', $html);
        self::assertStringContainsString('js/dashboard.js', $html);
        self::assertStringContainsString('css/dashboard.css', $html);
    }

    public function testMenusListRendersTheConfiguredIntroInThePageHeader(): void
    {
        // The intro is the PageHeader intro: the UiKit page_header partial renders it (the contract stub partial
        // overrides that partial, so the view model is asserted here; the fixture "menus list (intro)" runs the contract).
        $context = DashboardPageFixtures::index(false, '', 'dashboard.menus_intro');
        $intro   = $context['page']->header->intro;
        self::assertInstanceOf(Label::class, $intro);
        self::assertSame('dashboard.menus_intro', $intro->key);
        self::assertSame('Navigation menus of the site: edit their items, order and permissions, or export and import them as JSON.', $this->createTwig()->getExtension(TranslationExtension::class)->getTranslator()->trans($intro->key, [], $intro->domain, 'en'));

        $this->renderPage(new PageFixture(self::VIEWS . 'index.html.twig', $context));
        self::assertNull(DashboardPageFixtures::index()['page']->header->intro, 'No intro by default (BC).');
    }

    public function testSearchKeepsTheTermAndOffersClear(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'index.html.twig', DashboardPageFixtures::index(false, 'side')))->html;

        self::assertStringContainsString('value="side"', $html);
        self::assertStringContainsString('Clear filters', $html);
        self::assertStringContainsString('placeholder="Search by code or name…"', $html);
    }

    public function testMenuDetailRendersPropertiesItemsAndRowActions(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'show.html.twig', DashboardPageFixtures::show()))->html;

        self::assertStringContainsString('App\\Security\\MenuPermissionChecker', $html);
        self::assertStringContainsString('nav flex-column', $html, 'Stored menu CSS classes are listed as data.');
        self::assertStringContainsString('id="item-13"', $html);
        self::assertStringContainsString('app_post {&quot;slug&quot;:&quot;hello&quot;}', $html);
        self::assertStringContainsString('href="https://blog.example.test"', $html);
        self::assertStringContainsString('Non-unanimous', $html);
        self::assertStringContainsString('nowo-dashboard-menu-depth-1', $html);
        self::assertStringContainsString('action="/admin/menus/1/item/11/move-up"', $html);
        self::assertStringContainsString('value="token-item_move_up_11"', $html);
        self::assertStringContainsString('action="/admin/menus/1/items/reindex-positions"', $html);
        self::assertStringContainsString('Re-index positions (step 100)', $html);
        self::assertStringContainsString('href="/admin/menus/1/item/new?parent=12"', $html, 'Links can have children.');
        self::assertStringContainsString('href="/admin/menus/1/item/11/edit?section=icon"', $html);
        self::assertStringContainsString('Delete &quot;Latest post&quot; and its children?', $html);
        self::assertStringContainsString('href="/admin/menus/1/items/reorder"', $html);
        self::assertStringContainsString('"menuId":1', $html);
    }

    public function testMenuDetailWithoutItemsOffersAddItem(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'show.html.twig', DashboardPageFixtures::show(true)))->html;

        self::assertStringContainsString('No items.', $html);
        self::assertStringNotContainsString('/items/reorder"', $html);
        self::assertStringNotContainsString('reindex-positions', $html);
        self::assertGreaterThanOrEqual(2, substr_count($html, 'href="/admin/menus/1/item/new"'));
    }

    public function testReorderPageKeepsTheSortableHooks(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'show_items_reorder.html.twig', DashboardPageFixtures::reorder()))->html;

        self::assertStringContainsString('id="nowo-menu-sortable-root"', $html);
        self::assertStringContainsString('id="nowo-menu-sortable-form"', $html);
        self::assertStringContainsString('id="nowo-menu-sortable-payload"', $html);
        self::assertStringContainsString('data-parent-id="12"', $html);
        self::assertStringContainsString('Apply tree order', $html);
        self::assertStringContainsString('Drag reorder', $html);
    }

    public function testFormPagesRenderTheBundleFieldsets(): void
    {
        $menu = $this->renderPage(new PageFixture(self::VIEWS . 'menu_form.html.twig', DashboardPageFixtures::menuForm(true)))->html;
        self::assertStringContainsString('id="menu_definition_section"', $menu);
        self::assertStringContainsString('id="menu_config_section"', $menu);
        self::assertStringContainsString('href="/admin/menus/1"', $menu, 'Cancel goes back to the menu.');
        self::assertStringContainsString('Update', $menu);

        $config = $this->renderPage(new PageFixture(self::VIEWS . 'menu_form.html.twig', DashboardPageFixtures::menuForm(true, 'config')))->html;
        self::assertStringContainsString('name="_section" value="config"', $config);

        $item = $this->renderPage(new PageFixture(self::VIEWS . 'item_form.html.twig', DashboardPageFixtures::itemForm(true)))->html;
        self::assertStringContainsString('id="modal-item-form-container"', $item);
        self::assertStringContainsString('id="item_basic_section"', $item);
        self::assertStringContainsString('"itemFormPage":true', $item);

        $error = $this->renderPage(new PageFixture(self::VIEWS . 'item_form.html.twig', DashboardPageFixtures::itemForm(false, null, true)))->html;
        self::assertStringContainsString('This value should not be blank.', $error);
    }

    public function testItemLabelsRenderAsLocaleTabsWithTheDefaultLocaleFirst(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'item_form.html.twig', DashboardPageFixtures::itemForm(true)))->html;
        $id   = 'nowo-dashboard-menu-item-labels';

        self::assertStringContainsString('data-testid="' . $id . '"', $html);
        self::assertStringContainsString('data-nowo-ui-locale-tabs', $html);
        self::assertStringContainsString('aria-label="Label by language"', $html);
        self::assertLessThan(strpos($html, 'id="tab-' . $id . '-en"'), strpos($html, 'id="tab-' . $id . '-es"'));
        // default locale (es) first and marked; the request locale (en) is the open tab
        self::assertMatchesRegularExpression('/id="tab-' . $id . '-es"[^>]*aria-selected="false"[^>]*data-default/s', $html);
        self::assertMatchesRegularExpression('/id="tab-' . $id . '-en"[^>]*aria-selected="true"/s', $html);
        self::assertSame(1, substr_count($html, 'name="menu_item[basic][label_es]"'));
        self::assertSame(1, substr_count($html, 'name="menu_item[basic][label_en]"'));
        self::assertGreaterThan(strpos($html, 'id="tab-' . $id . '-en-panel"'), strpos($html, 'name="menu_item[basic][label_en]"'));
        self::assertStringNotContainsString('nowo-dashboard-menu-locales', $html);
        self::assertStringNotContainsString('data-model=', $html, 'page form: no Live bindings');
        self::assertStringContainsString('id="item_basic_label_icon_fields" class="nowo-dashboard-menu-rows"', $html);
        // the new item page (minimal section) has no translations
        $new = $this->renderPage(new PageFixture(self::VIEWS . 'item_form.html.twig', DashboardPageFixtures::itemForm(false)))->html;
        self::assertStringNotContainsString($id, $new);
    }

    public function testInvalidLabelLocaleTabIsMarkedAndOpened(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'item_form.html.twig', DashboardPageFixtures::itemForm(true, null, false, 'es')))->html;
        $id   = 'nowo-dashboard-menu-item-labels';

        // the invalid tab (es) opens instead of the request locale (en)
        self::assertStringContainsString('data-nowo-ui-tabs-active="' . $id . '-es"', $html);
        self::assertMatchesRegularExpression('/id="tab-' . $id . '-es"[^>]*aria-selected="true"[^>]*data-invalid/s', $html);
        self::assertMatchesRegularExpression('/id="tab-' . $id . '-en"[^>]*aria-selected="false"/s', $html);
        self::assertMatchesRegularExpression('/<input[^>]*name="menu_item\[basic\]\[label_es\]"[^>]*aria-invalid="true"/s', $html);
        self::assertStringContainsString('This value is too long.', $html);
    }

    public function testLiveComponentLabelTabsKeepTheirDataModelBindings(): void
    {
        $item = DashboardPageFixtures::items()[1];
        $item->setTranslations(['es' => 'Inicio', 'de' => 'Startseite']);
        $form = DashboardPageFixtures::formFactory()->create(MenuItemType::class, $item, [
            'available_locales' => ['en', 'es'],
            'csrf_token_id'     => 'submit',
            'section'           => 'basic',
            'live_model'        => true,
        ]);
        $this_ = new class {
            public function getItemType(): string
            {
                return 'link';
            }
        };
        $attributes = new class {
            /** @param array<string, string> $defaults */
            public function defaults(array $defaults): Markup
            {
                return new Markup('data-live-stub class="' . $defaults['class'] . '"', 'UTF-8');
            }
        };

        $html = $this->createTwig()->render('@NowoDashboardMenuBundle/components/ItemFormLiveComponent.html.twig', [
            'form'         => $form->createView(),
            'this'         => $this_,
            'attributes'   => $attributes,
            'isEdit'       => true,
            'locales'      => ['en', 'es'],
            'sectionFocus' => 'basic',
            'embedded'     => true,
        ]);
        $id = 'nowo-dashboard-menu-item-labels';

        self::assertStringContainsString('data-live-stub class="nowo-dashboard-menu-rows"', $html);
        self::assertMatchesRegularExpression('/id="tab-' . $id . '-es"[^>]*data-default/s', $html);
        self::assertMatchesRegularExpression('/<input[^>]*name="menu_item\[basic\]\[label_es\]"[^>]*data-model="on\(change\)\|menu_item\.basic\.label_es"[^>]*value="Inicio"/s', $html);
        self::assertMatchesRegularExpression('/<input[^>]*name="menu_item\[basic\]\[label_en\]"[^>]*data-model="on\(change\)\|menu_item\.basic\.label_en"/s', $html);
        self::assertStringContainsString('data-model="on(change)|menu_item.basic.label"', $html);
        // the tabs runtime attributes the MutationObserver re-syncs after a Live morph
        self::assertStringContainsString('data-nowo-ui-tabs data-nowo-ui-tabs-upgrade data-nowo-ui-tabs-active="' . $id . '-en"', $html);
    }

    public function testLiveItemPageIsABodyOnlyFormPage(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'item_form.html.twig', DashboardPageFixtures::itemFormLive()))->html;

        self::assertStringContainsString('<live-component name="dashboard_menu_item_form"', $html);
        self::assertStringNotContainsString('&quot;embedded&quot;:true', $html);
        self::assertStringContainsString('&quot;cancelHref&quot;:&quot;\/admin\/menus\/1&quot;', $html);
        self::assertSame(0, substr_count($html, '<form'), 'UiKit 2.2 body-only: the component renders its own form.');
        self::assertStringNotContainsString('data-nowo-ui-region="form"', $html);
    }

    public function testCopyAndImportShowTheirHelpText(): void
    {
        $copy = $this->renderPage(new PageFixture(self::VIEWS . 'copy_menu.html.twig', DashboardPageFixtures::copyForm()))->html;
        self::assertStringContainsString('value="sidebar_copy"', $copy);

        $import = $this->renderPage(new PageFixture(self::VIEWS . 'import.html.twig', DashboardPageFixtures::importForm()))->html;
        self::assertStringContainsString('enctype="multipart/form-data"', $import);
        self::assertStringContainsString('name="import_menu[strategy]"', $import);
    }

    public function testDialogFragmentsRenderWithoutTheLayout(): void
    {
        $twig = $this->createTwig();

        $menu = $twig->render(self::VIEWS . '_menu_form_partial.html.twig', DashboardPageFixtures::menuForm(true, 'basic'));
        self::assertStringNotContainsString('stub-layout', $menu);
        self::assertStringContainsString('data-nowo-ui-dialog-close', $menu);
        self::assertStringContainsString('id="menu_definition_section"', $menu);

        $item = $twig->render(self::VIEWS . '_item_form_partial.html.twig', DashboardPageFixtures::itemForm(false));
        self::assertStringContainsString('id="modal-item-form-container"', $item);
        self::assertStringContainsString('name="_section" value="minimal"', $item);

        $copy = $twig->render(self::VIEWS . '_copy_menu_partial.html.twig', DashboardPageFixtures::copyForm());
        self::assertStringContainsString('action="/admin/menus/1/copy"', $copy);

        $import = $twig->render(self::VIEWS . '_import_partial.html.twig', DashboardPageFixtures::importForm());
        self::assertStringContainsString('data-import-form="1"', $import);

        $live = $twig->render(self::VIEWS . '_item_form_live_partial.html.twig', DashboardPageFixtures::itemFormLive());
        self::assertStringContainsString('<live-component name="dashboard_menu_item_form"', $live);
        self::assertStringNotContainsString('&quot;embedded&quot;:true', $live);
    }

    public function testLegacyLayoutIsBridged(): void
    {
        $context                                      = DashboardPageFixtures::copyForm();
        $page                                         = $context['page'];
        $context['page']                              = new FormPage($page->header, $page->form, $page->submitLabel, $page->cancelHref, $page->cancelLabel, bundle: $page->bundle, layout: '@NowoDashboardMenuBundle/dashboard/_legacy_layout.html.twig');
        $context['nowo_dashboard_menu_legacy_layout'] = 'host/legacy.html.twig';

        $html = $this->createTwig()->render(self::VIEWS . 'copy_menu.html.twig', $context);

        self::assertStringStartsWith('<legacy-layout><title>Copy menu</title>', $html);
        self::assertStringContainsString('<host-css/>', $html);
        self::assertStringContainsString('css/nowo-ui.css', $html);
        self::assertStringContainsString('js/nowo-ui-dialog.js', $html);
        self::assertStringContainsString('data-nowo-ui-bundle="NowoDashboardMenuBundle"', $html);
        self::assertStringContainsString('data-nowo-ui-page="form"', $html);
    }

    public function testBangNamespaceOverrideKeepsTheBundlePage(): void
    {
        $html = $this->renderPage(new PageFixture('host/index_override.html.twig', DashboardPageFixtures::index()))->html;

        self::assertStringContainsString('<p class="host-note">custom</p>', $html);
        self::assertStringContainsString('data-nowo-ui-page="list"', $html);
    }

    protected static function inlineTemplates(): array
    {
        return [
            // 1.x host layout (renders nowo_dashboard_menu_content): wrapped by the deprecated legacy bridge.
            'host/legacy.html.twig'         => '<legacy-layout><title>{% block dashboard_title %}{% endblock %}</title><head-css>{% block stylesheets %}<host-css/>{% endblock %}</head-css>{% block nowo_dashboard_menu_content %}{% endblock %}<js>{% block javascripts %}{% endblock %}</js></legacy-layout>',
            'host/index_override.html.twig' => "{% extends '@!NowoDashboardMenuBundle/dashboard/index.html.twig' %}{% block nowo_ui_list_after %}<p class=\"host-note\">custom</p>{% endblock %}",
        ];
    }

    protected function configureTwig(Environment $twig): void
    {
        $urls = DashboardPageFixtures::urls();
        $twig->addExtension(new LocaleTabsTwigExtension());
        $twig->addFunction(new TwigFunction('path', static fn (string $name, array $parameters = []): string => $urls->generate($name, $parameters)));
        $twig->addFilter(new TwigFilter('dashboard_menu_icon_name', static fn (?string $name): string => (string) $name));
        $twig->addFunction(new TwigFunction('ux_icon', static fn (string $name, array $attributes = []): Markup => new Markup('<svg data-icon="' . htmlspecialchars($name) . '"></svg>', 'UTF-8')));
        // Live component stub: name + props (the real component is rendered by symfony/ux-live-component).
        $twig->addFunction(new TwigFunction('component', static function (string $name, array $props = []): Markup {
            $data = ['embedded' => $props['embedded'] ?? false, 'sectionFocus' => $props['sectionFocus'] ?? null, 'cancelHref' => $props['cancelHref'] ?? null];

            return new Markup('<live-component name="' . htmlspecialchars($name) . '" data-props="' . htmlspecialchars((string) json_encode($data)) . '"></live-component>', 'UTF-8');
        }));
    }

    protected static function templatePaths(): array
    {
        $views = dirname(__DIR__, 2) . '/src/Resources/views';

        return [
            'NowoDashboardMenuBundle'  => $views,
            '!NowoDashboardMenuBundle' => $views,
            'NowoFormKitBundle'        => dirname(__DIR__, 2) . '/vendor/nowo-tech/form-kit-bundle/src/Resources/views',
        ];
    }

    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 2) . '/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 2) . '/docs/TEMPLATES.md';
    }
}
