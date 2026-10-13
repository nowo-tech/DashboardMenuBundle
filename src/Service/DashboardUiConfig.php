<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use function array_keys;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;

/**
 * Admin dashboard chrome hooks ({@code nowo_dashboard_menu.dashboard.ui}): CSS class map, partial paths,
 * page/partial template overrides and a few layout switches. Every hook is opt-in: with the defaults the
 * dashboard renders exactly as before (UiKit macros driven by {@code css_framework}).
 *
 * Immutable value object (worker-safe): built once from the container parameter.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DashboardUiConfig
{
    /** Class-map keys hosts may set (null / missing = bundle default for the current css_framework). */
    public const CLASS_KEYS = [
        'button_primary',
        'button_secondary',
        'button_danger',
        'button_link',
        'button_small',
        'page_header',
        'toolbar',
        'search_form',
        'search_input',
        'info_panel',
        'inline_form',
        'empty',
        'empty_action',
        'muted',
        'badge',
        'badge_base',
        'badge_type',
        'badge_unanimous',
        'badge_non_unanimous',
        'list',
        'list_item',
        'table_wrap',
        'table',
        'table_header_row',
        'table_row',
        'row_actions',
        'row_action',
        'row_action_danger',
        'row_action_disabled',
        'sortable_panel',
        'sortable_header',
        'sortable_body',
        'modal_loading',
        'modal_form',
        'modal_content',
        'modal_section',
        'modal_message',
        'modal_actions',
    ];

    /** Default partials (relative to the bundle namespace). */
    public const DEFAULT_PARTIALS = [
        'pagination'       => '@NowoDashboardMenuBundle/dashboard/_pagination.html.twig',
        'row_action_inner' => '@NowoDashboardMenuBundle/dashboard/_row_action_inner.html.twig',
        'modal'            => '@NowoDashboardMenuBundle/dashboard/_modal.html.twig',
    ];

    /** Default page / partial templates rendered by the dashboard controller. */
    public const DEFAULT_TEMPLATES = [
        'index'              => '@NowoDashboardMenuBundle/dashboard/index.html.twig',
        'show'               => '@NowoDashboardMenuBundle/dashboard/show.html.twig',
        'show_items_reorder' => '@NowoDashboardMenuBundle/dashboard/show_items_reorder.html.twig',
        'menu_form_partial'  => '@NowoDashboardMenuBundle/dashboard/_menu_form_partial.html.twig',
        'item_form_partial'  => '@NowoDashboardMenuBundle/dashboard/_item_form_partial.html.twig',
        'copy_menu_partial'  => '@NowoDashboardMenuBundle/dashboard/_copy_menu_partial.html.twig',
        'import_partial'     => '@NowoDashboardMenuBundle/dashboard/_import_partial.html.twig',
        'modals'             => '@NowoDashboardMenuBundle/dashboard/_menu_dashboard_modals.html.twig',
    ];

    public const INDEX_VIEWS = ['list', 'table'];

    public const ACTIONS_COLUMNS = ['first', 'last'];

    public const ROW_ACTION_DISPLAYS = ['icon', 'text', 'icon_text'];

    /** @var array<string, string> */
    private array $classes;

    /** @var array<string, string> */
    private array $partials;

    /** @var array<string, string> */
    private array $templates;

    /** @var list<string> */
    private array $formThemes;

    private bool $includeUiKitCss;

    private bool $headerActionsInLayout;

    private string $indexView;

    private string $actionsColumn;

    private ?string $rowActionDisplay;

    private ?string $regionAttribute;

    /**
     * @param array<string, mixed> $config Normalized {@code dashboard.ui} config (unknown / null entries ignored)
     */
    public function __construct(array $config = [])
    {
        $this->classes   = $this->stringMap($config['classes'] ?? [], self::CLASS_KEYS, true);
        $this->partials  = $this->stringMap($config['partials'] ?? [], array_keys(self::DEFAULT_PARTIALS), false) + self::DEFAULT_PARTIALS;
        $this->templates = $this->stringMap($config['templates'] ?? [], array_keys(self::DEFAULT_TEMPLATES), false) + self::DEFAULT_TEMPLATES;

        $themes = [];
        foreach (is_array($config['form_themes'] ?? null) ? $config['form_themes'] : [] as $theme) {
            if (is_string($theme) && $theme !== '') {
                $themes[] = $theme;
            }
        }
        $this->formThemes = $themes;

        $this->includeUiKitCss       = is_bool($config['include_ui_kit_css'] ?? null) ? $config['include_ui_kit_css'] : true;
        $this->headerActionsInLayout = is_bool($config['header_actions_in_layout'] ?? null) ? $config['header_actions_in_layout'] : false;
        $this->indexView             = $this->choice($config['index_view'] ?? null, self::INDEX_VIEWS) ?? 'list';
        $this->actionsColumn         = $this->choice($config['actions_column'] ?? null, self::ACTIONS_COLUMNS) ?? 'first';
        $this->rowActionDisplay      = $this->choice($config['row_action_display'] ?? null, self::ROW_ACTION_DISPLAYS);
        $region                      = $config['region_attribute'] ?? null;
        $this->regionAttribute       = is_string($region) && $region !== '' ? $region : null;
    }

    /**
     * Configured class for {@code $key}, or {@code $default} (the bundle markup for the current css_framework).
     * An explicitly configured empty string is honoured (drops the bundle class).
     */
    public function cssClass(string $key, string $default = ''): string
    {
        return $this->classes[$key] ?? $default;
    }

    public function partial(string $name): string
    {
        return $this->partials[$name] ?? '';
    }

    public function template(string $name): string
    {
        return $this->templates[$name] ?? '';
    }

    /**
     * Twig global {@code nowo_dashboard_menu_ui}.
     *
     * @return array{
     *     classes: array<string, string>,
     *     partials: array<string, string>,
     *     templates: array<string, string>,
     *     form_themes: list<string>,
     *     include_ui_kit_css: bool,
     *     header_actions_in_layout: bool,
     *     index_view: string,
     *     actions_column: string,
     *     row_action_display: string|null,
     *     region_attribute: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'classes'                  => $this->classes,
            'partials'                 => $this->partials,
            'templates'                => $this->templates,
            'form_themes'              => $this->formThemes,
            'include_ui_kit_css'       => $this->includeUiKitCss,
            'header_actions_in_layout' => $this->headerActionsInLayout,
            'index_view'               => $this->indexView,
            'actions_column'           => $this->actionsColumn,
            'row_action_display'       => $this->rowActionDisplay,
            'region_attribute'         => $this->regionAttribute,
        ];
    }

    /**
     * @param list<string> $allowed
     *
     * @return array<string, string>
     */
    private function stringMap(mixed $raw, array $allowed, bool $allowEmpty): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $map = [];
        foreach ($allowed as $key) {
            $value = $raw[$key] ?? null;
            if (is_string($value) && ($allowEmpty || $value !== '')) {
                $map[$key] = $value;
            }
        }

        return $map;
    }

    /**
     * @param list<string> $choices
     */
    private function choice(mixed $value, array $choices): ?string
    {
        return is_string($value) && in_array($value, $choices, true) ? $value : null;
    }
}
