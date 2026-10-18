<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection;

use Nowo\DashboardMenuBundle\Enum\CssFramework;
use Nowo\DashboardMenuBundle\Enum\IconSet;
use Nowo\DashboardMenuBundle\Service\DashboardUiConfig;
use Nowo\DashboardMenuBundle\View\DashboardWebUi;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function implode;
use function is_array;
use function is_string;

/**
 * Configuration for Dashboard Menu Bundle.
 *
 * In YAML you can define: project, locales, default_locale, api, dashboard.
 * All menu registration and CSS classes are stored in the database; YAML only
 * provides defaults and selector options (e.g. for CSS classes).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_dashboard_menu';

    private const DEPRECATION_PACKAGE = 'nowo-tech/dashboard-menu-bundle';

    private const DEPRECATION_VERSION = '2.7';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->beforeNormalization()
                ->ifArray()
                ->then(static function (array $v): array {
                    $dashboard = is_array($v['dashboard'] ?? null) ? $v['dashboard'] : [];
                    $security  = is_array($v['security'] ?? null) ? $v['security'] : [];

                    if (array_key_exists('required_role', $dashboard) && !array_key_exists('access_roles', $security)) {
                        $role = $dashboard['required_role'];
                        if ($role === null || $role === '') {
                            $security['access_roles'] = [];
                        } elseif (is_string($role)) {
                            $security['access_roles'] = [$role];
                        }
                    }

                    $v['security'] = $security;

                    // REQ-UI-003 canonical keys: dashboard.enabled / path_prefix / layout_template → web_ui.* (the
                    // canonical key wins); dashboard.ui.templates → web_ui.templates (only values a host changed).
                    $webUi = is_array($v['web_ui'] ?? null) ? $v['web_ui'] : [];
                    foreach (['enabled', 'path_prefix', 'layout_template'] as $key) {
                        if (array_key_exists($key, $dashboard) && !array_key_exists($key, $webUi)) {
                            $webUi[$key] = $dashboard[$key];
                        }
                    }
                    // The 1.x default layout (CSS frameworks from CDNs) is gone: that value now means the UiKit layout.
                    if (($webUi['layout_template'] ?? null) === DashboardWebUi::LEGACY_LAYOUT) {
                        $webUi['layout_template'] = null;
                    }
                    $legacyTemplates = is_array($dashboard['ui']['templates'] ?? null) ? $dashboard['ui']['templates'] : [];
                    foreach ($legacyTemplates as $page => $template) {
                        if (
                            is_string($page) && is_string($template) && $template !== ''
                            && isset(DashboardWebUi::TEMPLATES[$page]) && $template !== DashboardWebUi::TEMPLATES[$page]
                            && !isset($webUi['templates'][$page])
                        ) {
                            $webUi['templates'][$page] = $template;
                        }
                    }
                    if ($webUi !== []) {
                        $v['web_ui'] = $webUi;
                    }

                    return $v;
                })
            ->end()
            ->children()
                ->scalarNode('project')
                    ->info('Optional project identifier to differentiate menus when multiple apps share the same DB')
                    ->defaultNull()
                ->end()
                ->arrayNode('doctrine')
                    ->info('Doctrine DBAL connection and table prefix for menu entities.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('connection')
                            ->info('Name of the Doctrine DBAL connection to use (e.g. default, or a custom connection).')
                            ->defaultValue('default')
                        ->end()
                        ->scalarNode('table_prefix')
                            ->info('Prefix prepended to table names (dashboard_menu, dashboard_menu_item). Empty = no prefix.')
                            ->defaultValue('')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->info('Cache for the resolved menu tree (avoids N+1 and repeated DB hits). Uses filesystem when cache_pool is the default.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('ttl')
                            ->info('Time-to-live in seconds for the menu tree cache. Minimum 0 (0 = immediate expiry for saved items; behaviour depends on the PSR-6 pool).')
                            ->min(0)
                            ->defaultValue(60)
                        ->end()
                        ->scalarNode('pool')
                            ->info('Cache pool name (e.g. cache.app). Set to null or empty to disable tree cache.')
                            ->defaultValue('cache.app')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('icon_library_prefix_map')
                    ->info('Map full icon library names to short prefixes used when rendering the menu (e.g. bootstrap-icons → bi). Applied before passing the icon to ux_icon().')
                    ->useAttributeAsKey('library')
                    ->scalarPrototype()->end()
                    ->defaultValue(['bootstrap-icons' => 'bi'])
                ->end()
                ->arrayNode('locales')
                    ->info('List of enabled locales for menu labels. When set, the request locale is used only if in this list; otherwise default_locale or the first locale is used.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('default_locale')
                    ->info('Fallback locale when the request locale is not in locales. If null, the first entry in locales is used.')
                    ->defaultNull()
                ->end()
                ->arrayNode('permission_checker_choices')
                    ->info('Ordered list of permission checker service IDs (FQCN) for the dashboard dropdown. Each must implement MenuPermissionCheckerInterface. Tagged services are merged automatically; this list adds or orders them. The visible label is the service id unless the tag defines label= (or class metadata). Legacy YAML with only `order:` (and ignored `labels:`) is normalized to this list.')
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function (array $v): array {
                            if (isset($v['order']) && is_array($v['order'])) {
                                return array_values(array_filter($v['order'], is_string(...)));
                            }
                            if (array_is_list($v)) {
                                return array_values(array_filter($v, is_string(...)));
                            }

                            return array_values(array_filter(array_keys($v), is_string(...)));
                        })
                    ->end()
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('menu_link_resolver_choices')
                    ->info('Ordered list of MenuLinkResolverInterface service IDs for the "Link resolver" dropdown (item type service). Tagged resolvers are merged; this list adds or orders them. The visible label is the service id unless the tag defines label=. Legacy YAML with only `order:` is normalized to this list.')
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function (array $v): array {
                            if (isset($v['order']) && is_array($v['order'])) {
                                return array_values(array_filter($v['order'], is_string(...)));
                            }
                            if (array_is_list($v)) {
                                return array_values(array_filter($v, is_string(...)));
                            }

                            return array_values(array_filter(array_keys($v), is_string(...)));
                        })
                    ->end()
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('api')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Register the JSON API route nowo_dashboard_menu_api (requires importing the bundle routes.yaml). When false, the route is not registered.')
                            ->defaultValue(true)
                        ->end()
                        ->scalarNode('path_prefix')
                            ->info('Path prefix of the JSON API route: GET {path_prefix}/{code}.')
                            ->defaultValue('/api/menu')
                            ->validate()
                                ->ifTrue(static fn (mixed $v): bool => !is_string($v))
                                ->thenInvalid('api.path_prefix must be a string (e.g. "/api/menu"), got %s.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('menu_collapse')
                    ->info('Bootstrap-free nested collapse (2.6.0): menu.html.twig marks the menu root with data-dashboard-menu-collapse* attributes for public/js/menu-collapse.js. Opt-in; the script must be included by the host. See docs/USAGE.md "Nested collapse without Bootstrap JS".')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Emit the data-dashboard-menu-collapse* attributes on the rendered menu root (overridable per include with the menuCollapse variable). Default false: markup unchanged.')
                            ->defaultFalse()
                        ->end()
                        ->enumNode('mode')
                            ->info('"auto" (default): the script stands down while Bootstrap Collapse JS is loaded (no double toggle) and only restores / remembers state. "always": the script takes the toggles over (drops their data-bs-toggle).')
                            ->values(['auto', 'always'])
                            ->defaultValue('auto')
                        ->end()
                        ->scalarNode('storage_key')
                            ->info('localStorage key holding { <menu code>: { <panel id>: open } }. Empty string disables persistence.')
                            ->defaultValue('nowo.dashboardMenu.navCollapse')
                            ->validate()
                                ->ifTrue(static fn (mixed $v): bool => !is_string($v))
                                ->thenInvalid('menu_collapse.storage_key must be a string (use "" to disable persistence), got %s.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('dashboard')
                    ->info('Admin dashboard to manage menus and items. Import routes with prefix (e.g. /admin/menus).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Deprecated alias of web_ui.enabled.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_dashboard_menu.web_ui.enabled" instead.')
                        ->end()
                        ->scalarNode('layout_template')
                            ->info('Deprecated alias of web_ui.layout_template (the 1.x default layout is removed: null = UiKit layout).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_dashboard_menu.web_ui.layout_template" (null = nowo_ui_kit.layouts.admin) instead.')
                        ->end()
                        ->scalarNode('css_framework')
                            ->defaultValue(CssFramework::Bootstrap5->value)
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, set "nowo_ui_kit.css_framework" instead.')
                            ->info('Deprecated: the dashboard pages follow nowo_ui_kit.css_framework (seeded from this value when set here and not in nowo_ui_kit).')
                            ->validate()
                                ->ifNotInArray(CssFramework::values())
                                ->thenInvalid('Invalid css_framework "%s".')
                            ->end()
                        ->end()
                        ->scalarNode('icon_set')
                            ->defaultValue(IconSet::BootstrapIcons->value)
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, set "nowo_ui_kit.icon_set" instead.')
                            ->info('Deprecated: row action icons follow nowo_ui_kit.icon_set (seeded from this value when set here and not in nowo_ui_kit).')
                            ->validate()
                                ->ifNotInArray(IconSet::values())
                                ->thenInvalid('Invalid icon_set "%s".')
                            ->end()
                        ->end()
                        ->scalarNode('path_prefix')
                            ->info('Deprecated alias of web_ui.path_prefix (the URL prefix itself is set when importing routes_dashboard.yaml).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, '2.0', 'The option "%path%" is deprecated. Use "nowo_dashboard_menu.web_ui.path_prefix" and configure the same prefix in your app routing (e.g. config/routes.yaml) when importing @NowoDashboardMenuBundle/Resources/config/routes_dashboard.yaml.')
                        ->end()
                        ->arrayNode('route_name_exclude_patterns')
                            ->info('Regex patterns to exclude route names from the route selector (e.g. "^_" to hide internal routes, "^(web_profiler|_)" for profiler).')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('pagination')
                            ->info('Pagination for the menus list in the dashboard.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->info('When true, the menus list is paginated.')
                                    ->defaultValue(true)
                                ->end()
                                ->integerNode('per_page')
                                    ->info('Number of menus per page when pagination is enabled.')
                                    ->min(1)
                                    ->max(500)
                                    ->defaultValue(20)
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('modals')
                            ->info('Modal dialog size per type: normal (default), lg, or xl (Bootstrap 5).')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('menu_form')
                                    ->info('New menu and edit menu modals.')
                                    ->defaultValue('normal')
                                    ->validate()
                                        ->ifNotInArray(['normal', 'lg', 'xl'])
                                        ->thenInvalid('Must be normal, lg, or xl.')
                                    ->end()
                                ->end()
                                ->scalarNode('copy')
                                    ->info('Copy menu modal.')
                                    ->defaultValue('normal')
                                    ->validate()
                                        ->ifNotInArray(['normal', 'lg', 'xl'])
                                        ->thenInvalid('Must be normal, lg, or xl.')
                                    ->end()
                                ->end()
                                ->scalarNode('item_form')
                                    ->info('Add/edit item modal.')
                                    ->defaultValue('lg')
                                    ->validate()
                                        ->ifNotInArray(['normal', 'lg', 'xl'])
                                        ->thenInvalid('Must be normal, lg, or xl.')
                                    ->end()
                                ->end()
                                ->scalarNode('delete')
                                    ->info('Delete confirmation modals.')
                                    ->defaultValue('normal')
                                    ->validate()
                                        ->ifNotInArray(['normal', 'lg', 'xl'])
                                        ->thenInvalid('Must be normal, lg, or xl.')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->append($this->dashboardUiNode())
                        ->scalarNode('icon_selector_script_url')
                            ->info('Optional URL of the icon-selector Stimulus/script asset. When set, the dashboard layout sets window.dashboardMenuIconSelectorScriptUrl so the item form modal can init the icon selector. Use with nowo-tech/icon-selector-bundle and Symfony UX (Stimulus).')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('stimulus_script_url')
                            ->info('Optional URL of a script that loads Stimulus and the Live controller and sets window.Stimulus. When set, the dashboard layout includes this script so the item form Live Component works in the modal. Use the bundle default (null = use bundled script), or your app entry (e.g. Vite) that exposes window.Stimulus.')
                            ->defaultNull()
                        ->end()
                        ->integerNode('import_max_bytes')
                            ->info('Maximum size in bytes for JSON import file uploads. Default 2 MiB. Prevents DoS from very large uploads.')
                            ->min(1024)
                            ->defaultValue(2097152)
                        ->end()
                        ->integerNode('position_step')
                            ->info('Gap used when re-indexing item positions from the menu detail dashboard (e.g. 100 → positions 100, 200, 300 per sibling group). Minimum 1.')
                            ->min(1)
                            ->defaultValue(100)
                        ->end()
                        ->scalarNode('required_role')
                            ->info('DEPRECATED: use security.access_roles. When set (e.g. ROLE_ADMIN), maps to security.access_roles for BC.')
                            ->defaultNull()
                            ->setDeprecated('nowo-tech/dashboard-menu-bundle', '1.0', 'Use "nowo_dashboard_menu.security.access_roles" instead of "dashboard.required_role".')
                        ->end()
                        ->arrayNode('import_export_rate_limit')
                            ->info('Optional rate limit for import and export actions: limit requests per interval per user/IP. E.g. { limit: 10, interval: 60 } = 10 per minute.')
                            ->canBeDisabled()
                            ->children()
                                ->integerNode('limit')
                                    ->min(1)
                                    ->defaultValue(10)
                                ->end()
                                ->integerNode('interval')
                                    ->info('Time window in seconds.')
                                    ->min(1)
                                    ->defaultValue(60)
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('permission_key_choices')
                            ->info('List of permission keys shown in the item form "Permission key" selector (e.g. authenticated, admin, path:/). When set, the field becomes a dropdown with autocomplete; when empty, a plain text input is used.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('id_options')
                            ->info('List of HTML id values for the root <ul> when editing a menu. When set, the "ulId" field becomes a dropdown; when empty, it becomes a plain text input.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->scalarNode('icon_size')
                            ->info('CSS size used to render menu item icons (applied to SVG via width/height and to legacy icons via `font-size`). Example: `1em`, `16px`, `24px`.')
                            ->defaultValue('1em')
                        ->end()
                        ->booleanNode('autocomplete_bootstrap')
                            ->info('When true (default), dashboard.js registers Symfony UX Autocomplete from esm.sh and injects the Tom Select CDN stylesheet if the host Stimulus app has not registered the controller. Set false when the host app ships UX Autocomplete and its own Tom Select styles (CSP without third-party origins). The script never re-registers a controller the host already registered.')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('item_span_active')
                            ->info('When true, the item label (non-section items) is wrapped in an extra <span> element. This controls rendering of the wrapper in menu.html.twig.')
                            ->defaultValue(false)
                        ->end()
                        ->arrayNode('css_class_options')
                            ->info('Lists of CSS classes shown as selectors in the dashboard when editing a menu. Override in app config to customize options.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('menu')
                                    ->info('CSS classes for the root <ul>.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['nav flex-column', 'nav flex-row', 'dropdown-menu dropdown-menu-end', 'nav flex-wrap gap-1'])
                                ->end()
                                ->arrayNode('item')
                                    ->info('CSS classes for each <li>.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['nav-item', 'dropdown-item', ''])
                                ->end()
                                ->arrayNode('link')
                                    ->info('CSS classes for each <a>.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['nav-link', 'dropdown-item', 'nav-link link-secondary'])
                                ->end()
                                ->arrayNode('children')
                                    ->info('CSS classes for nested <ul>.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['nav flex-column ms-2', 'nav flex-column', 'dropdown-menu'])
                                ->end()
                                ->arrayNode('section_children')
                                    ->info('CSS classes for the <ul> directly under itemType "section" (when set on the menu entity).')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['nav flex-column list-unstyled ps-0', 'nav flex-column ms-2', 'nav flex-column', ''])
                                ->end()
                                ->arrayNode('section_child_item')
                                    ->info('CSS classes for <li> when the item is a direct child of itemType "section" (when set on the menu entity).')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['nav-item ms-0', 'nav-item', ''])
                                ->end()
                                ->arrayNode('section_child_link')
                                    ->info('CSS classes for <a> when the link is a direct child of itemType "section" (when set on the menu entity).')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['nav-link d-flex align-items-center ps-0', 'nav-link py-0 d-flex align-items-center menu-title text-truncate ps-0', 'nav-link', ''])
                                ->end()
                                ->arrayNode('section_label')
                                    ->info('CSS classes for the <span> label of section items (itemType "section").')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['menu-section-label', ''])
                                ->end()
                                ->arrayNode('section')
                                    ->info('CSS classes applied to <li> when itemType is "section".')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['menu-section', 'navigation-header', ''])
                                ->end()
                                ->arrayNode('divider')
                                    ->info('CSS classes applied to <li> when itemType is "divider".')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['menu-divider', ''])
                                ->end()
                                ->arrayNode('span')
                                    ->info('CSS classes for the wrapper <span> of item labels (non-section items) when `item_span_active` is enabled.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['d-flex align-items-center flex-nowrap', ''])
                                ->end()
                                ->arrayNode('current')
                                    ->info('CSS classes for the current <a>.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['active', ''])
                                ->end()
                                ->arrayNode('branch_expanded')
                                    ->info('CSS classes for <li> when the current route is in its branch.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['active-branch', 'active-branch-has-children', ''])
                                ->end()
                                ->arrayNode('has_children')
                                    ->info('CSS classes for <li> when the item has children.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['dropdown', 'has-children', ''])
                                ->end()
                                ->arrayNode('expanded')
                                    ->info('CSS classes for <li> when its children block is open.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['expanded', ''])
                                ->end()
                                ->arrayNode('collapsed')
                                    ->info('CSS classes for <li> when its children block is closed.')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['collapsed', ''])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('web_ui')
                    ->info('REQ-UI-003 admin dashboard: pages extend the UiKit scaffolds; theme them once in nowo_ui_kit.theme (per-bundle exception: nowo_ui_kit.theme.bundles.NowoDashboardMenuBundle).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable the admin dashboard routes (import @NowoDashboardMenuBundle/Resources/config/routes_dashboard.yaml with your prefix).')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('path_prefix')
                            ->info('URL prefix the host imports routes_dashboard.yaml with (informational: lock it in security.access_control).')
                            ->defaultValue('/admin/menus')
                        ->end()
                        ->scalarNode('layout_template')
                            ->info('Layout the dashboard scaffolds extend instead of nowo_ui_kit.layouts.admin (null = UiKit layout). It must render the UiKit canonical blocks (nowo_ui_content…); a 1.x layout that renders "nowo_dashboard_menu_content" is bridged (deprecated).')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-page template override (page => template): ' . implode(', ', array_keys(DashboardWebUi::TEMPLATES)) . '. Prefer a thin {% extends \'@!NowoDashboardMenuBundle/…\' %} that overrides a data block.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('page')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => array_diff(array_keys($v), array_keys(DashboardWebUi::TEMPLATES)) !== [])
                                ->thenInvalid('Unknown nowo_dashboard_menu.web_ui.templates page in %s. Allowed: ' . implode(', ', array_keys(DashboardWebUi::TEMPLATES)) . '.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->info('Dashboard admin access control (REQ-UI-002). Host should also lock dashboard.path_prefix via security.access_control.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('access_checker')
                            ->defaultNull()
                            ->info('Optional service id implementing DashboardMenuAccessCheckerInterface. Null uses the built-in role checker.')
                        ->end()
                        ->arrayNode('access_roles')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                            ->info('User must be granted at least one role. Empty list denies access (fail-closed) unless security.access_checker is set.')
                        ->end()
                        ->booleanNode('allow_unauthenticated')
                            ->defaultFalse()
                            ->info('DEV/DEMO ONLY. When true, dashboard may load without SecurityBundle / without login. Production MUST keep false.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * {@code dashboard.ui}: admin chrome hooks (class map, partial paths, template overrides). All opt-in;
     * defaults keep the bundle markup for the configured css_framework.
     */
    private function dashboardUiNode(): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $node */
        $node = (new TreeBuilder('ui'))->getRootNode();
        $node
            ->info('Deprecated (2.7): 1.x admin chrome hooks. Theme the pages once with nowo_ui_kit.theme (classes are bridged into nowo_ui_kit.theme.bundles.NowoDashboardMenuBundle); templates map to web_ui.templates. See docs/UPGRADING.md.')
            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated: theme the dashboard with "nowo_ui_kit.theme" (classes → nowo_ui_kit.theme.bundles.NowoDashboardMenuBundle.classes, templates → nowo_dashboard_menu.web_ui.templates). See docs/UPGRADING.md.')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('include_ui_kit_css')
                    ->info('Append UiKit nowo-ui.css after the layout stylesheets (dashboard/base.html.twig). Set false when the host layout already loads it (re-appending reasserts UiKit :root tokens).')
                    ->defaultTrue()
                ->end()
                ->booleanNode('header_actions_in_layout')
                    ->info('When true, pages do not render their header (back link, title, primary actions) / toolbar in the body; the actions HTML is exposed as nowo_dashboard_menu_header_actions for the layout_template to place.')
                    ->defaultFalse()
                ->end()
                ->enumNode('index_view')
                    ->info('Menus list markup: "list" (default) or "table" (code / name / items / context / actions columns).')
                    ->values(DashboardUiConfig::INDEX_VIEWS)
                    ->defaultValue('list')
                ->end()
                ->enumNode('actions_column')
                    ->info('Position of the actions column in the items table (show page): "first" (default) or "last".')
                    ->values(DashboardUiConfig::ACTIONS_COLUMNS)
                    ->defaultValue('first')
                ->end()
                ->enumNode('row_action_display')
                    ->info('Row action content: null (default) = bundle SVG icons only; icon | text | icon_text render partials.row_action_inner (UiKit row_actions_display contract).')
                    ->values([null, ...DashboardUiConfig::ROW_ACTION_DISPLAYS])
                    ->defaultNull()
                ->end()
                ->scalarNode('region_attribute')
                    ->info('Optional attribute name (e.g. data-testid) set on page regions: dashboard-menu-menus-table, dashboard-menu-items-table, dashboard-menu-sortable.')
                    ->defaultNull()
                ->end()
                ->arrayNode('header_action_variants')
                    ->info('Per-action button variant for the page header actions (block dashboard_header_actions), e.g. { edit_menu: secondary }. Keys: ' . implode(', ', DashboardUiConfig::HEADER_ACTIONS) . '. Values: ' . implode('|', DashboardUiConfig::BUTTON_VARIANTS) . ' (class-map key button_<variant>, else UiKit ui.btn(variant)). Unset actions keep the bundle variant.')
                    ->useAttributeAsKey('action')
                    ->enumPrototype()->values(DashboardUiConfig::BUTTON_VARIANTS)->end()
                    ->defaultValue([])
                    ->validate()
                        ->ifTrue(static fn (array $map): bool => array_diff(array_keys($map), DashboardUiConfig::HEADER_ACTIONS) !== [])
                        ->thenInvalid('Unknown dashboard.ui.header_action_variants key in %s. Allowed keys: ' . implode(', ', DashboardUiConfig::HEADER_ACTIONS) . '.')
                    ->end()
                ->end()
                ->arrayNode('form_themes')
                    ->info('Form themes applied to every dashboard form (pages, modal bodies, Live item form).')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->append($this->classesNode())
                ->arrayNode('partials')
                    ->info('Partial templates the pages include (same variables as the bundle partials; see docs/USAGE.md).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('pagination')->defaultValue(DashboardUiConfig::DEFAULT_PARTIALS['pagination'])->end()
                        ->scalarNode('row_action_inner')->defaultValue(DashboardUiConfig::DEFAULT_PARTIALS['row_action_inner'])->end()
                        ->scalarNode('modal')->defaultValue(DashboardUiConfig::DEFAULT_PARTIALS['modal'])->end()
                    ->end()
                ->end()
                ->append($this->templatesNode())
            ->end();

        return $node;
    }

    private function classesNode(): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $node */
        $node = (new TreeBuilder('classes'))->getRootNode();
        $node
            ->info('CSS class map. Unset (null) = UiKit macro for css_framework; a string replaces the bundle class ("" drops it).')
            ->addDefaultsIfNotSet();
        $children = $node->children();
        foreach (DashboardUiConfig::CLASS_KEYS as $key) {
            $children->scalarNode($key)->defaultNull()->end();
        }
        $children->end();

        return $node;
    }

    private function templatesNode(): ArrayNodeDefinition
    {
        /** @var ArrayNodeDefinition $node */
        $node = (new TreeBuilder('templates'))->getRootNode();
        $node
            ->info('Page / partial templates rendered by the dashboard. Point to a host template that {% extends %} the bundle one and overrides blocks.')
            ->addDefaultsIfNotSet();
        $children = $node->children();
        foreach (DashboardUiConfig::DEFAULT_TEMPLATES as $key => $default) {
            $children->scalarNode($key)->defaultValue($default)->end();
        }
        $children->end();

        return $node;
    }
}
