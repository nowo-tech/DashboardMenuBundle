<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Controller\Dashboard;

/**
 * Route name constants for the dashboard controllers.
 * All dashboard controllers share the prefix 'nowo_dashboard_menu_dashboard_'.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DashboardRoutes
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

    private function __construct()
    {
    }

    /**
     * Full map of all dashboard routes: template key => route name.
     * Passed to templates as dashboard_routes.
     *
     * @return array<string, string>
     */
    public static function all(): array
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
}
