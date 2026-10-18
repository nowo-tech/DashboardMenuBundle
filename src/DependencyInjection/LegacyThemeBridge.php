<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection;

use Nowo\DashboardMenuBundle\Service\DashboardUiConfig;
use Nowo\DashboardMenuBundle\View\DashboardWebUi;

use function is_array;
use function is_string;
use function trim;

/**
 * Bridges the deprecated 1.x chrome hooks {@code dashboard.ui.classes} and {@code dashboard.ui.partials.pagination}
 * into the UiKit per-bundle theme exception {@code nowo_ui_kit.theme.bundles.NowoDashboardMenuBundle} (REQ-UI-003), so
 * hosts that customised the 1.x chrome keep their look for one minor version. Removed in 3.0.
 *
 * A 1.x class value replaced the bundle class (a complete framework class list), so it is bridged with
 * {@code framework_defaults: false}: the slot renders its semantic {@code nowo-ui-*} class plus exactly the host value
 * ({@code ""} keeps the semantic class only).
 */
final class LegacyThemeBridge
{
    public const BUNDLE = DashboardWebUi::BUNDLE;

    /** 1.x {@code dashboard.ui.classes} key => UiKit theme slot. Keys not listed have no slot (see UPGRADING). */
    public const CLASS_SLOTS = [
        'button_primary'      => 'btn_primary',
        'button_secondary'    => 'btn_secondary',
        'button_danger'       => 'btn_danger',
        'button_link'         => 'btn_link',
        'page_header'         => 'page_header',
        'toolbar'             => 'toolbar',
        'search_form'         => 'filters',
        'search_input'        => 'input',
        'info_panel'          => 'detail_list',
        'empty'               => 'empty',
        'muted'               => 'muted',
        'badge'               => 'badge_neutral',
        'table_wrap'          => 'table_wrap',
        'table'               => 'table',
        'table_header_row'    => 'table_head_row',
        'table_row'           => 'table_row',
        'row_actions'         => 'row_actions',
        'row_action'          => 'action',
        'row_action_danger'   => 'action_danger',
        'row_action_disabled' => 'row_action_disabled',
        'sortable_panel'      => 'card',
        'sortable_header'     => 'card_header',
        'sortable_body'       => 'card_body',
        'modal_form'          => 'form',
        'modal_actions'       => 'form_actions',
    ];

    /** 1.x {@code dashboard.ui.partials} key => UiKit theme partial name. */
    public const PARTIALS = [
        'pagination' => 'pagination',
    ];

    /**
     * UiKit theme exception for this bundle from a processed {@code dashboard.ui} node (empty array when nothing to bridge).
     *
     * @param array<string, mixed> $ui
     *
     * @return array{classes?: array<string, array{value: string, framework_defaults: false}>, partials?: array<string, string>}
     */
    public static function themeException(array $ui): array
    {
        $theme = [];

        $classes = [];
        $map     = is_array($ui['classes'] ?? null) ? $ui['classes'] : [];
        foreach (self::CLASS_SLOTS as $key => $slot) {
            $value = $map[$key] ?? null;
            if (!is_string($value)) {
                continue;
            }
            $classes[$slot] = ['value' => trim(($classes[$slot]['value'] ?? '') . ' ' . trim($value)), 'framework_defaults' => false];
        }
        if ($classes !== []) {
            $theme['classes'] = $classes;
        }

        $partials   = [];
        $configured = is_array($ui['partials'] ?? null) ? $ui['partials'] : [];
        foreach (self::PARTIALS as $key => $partial) {
            $template = $configured[$key] ?? null;
            if (is_string($template) && $template !== '' && $template !== DashboardUiConfig::DEFAULT_PARTIALS[$key]) {
                $partials[$partial] = $template;
            }
        }
        if ($partials !== []) {
            $theme['partials'] = $partials;
        }

        return $theme;
    }
}
