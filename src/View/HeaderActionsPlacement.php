<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\View;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\PageHeader;

use function property_exists;

/**
 * Where the header actions of the dashboard pages (New, Import, Export, Edit, Add item) are placed ({@code web_ui.header_actions}).
 *
 * {@see self::BUTTONS} (default): a button per action in the page header, as in every earlier release.
 * {@see self::PAGE_ACTIONS}: the page header uses UiKit's split layout and no action stays a filled primary button, so
 * every action lands in the square page-actions menu (the same {@code View\Action} objects: kind, target, POST form,
 * dialog opener and attributes are kept). A host that renders the compact header ({@code nowo_ui_kit.header}) reads the
 * same {@code PageHeader::$actions} for its own page-actions menu. Needs UiKit 2.21+ (split layout); an older UiKit
 * keeps the buttons.
 *
 * Stateless: nothing is memoised (worker safe).
 */
final class HeaderActionsPlacement
{
    public const BUTTONS = 'buttons';

    public const PAGE_ACTIONS = 'page_actions';

    public const MODES = [self::BUTTONS, self::PAGE_ACTIONS];

    /**
     * The header as is ({@see self::BUTTONS}) or, for {@see self::PAGE_ACTIONS}, a copy with UiKit's split layout and
     * its actions demoted ({@see self::menuActions()}).
     */
    public static function apply(string $mode, PageHeader $header): PageHeader
    {
        if ($mode !== self::PAGE_ACTIONS || $header->actions === [] || !property_exists(PageHeader::class, 'actionsLayout')) {
            return $header;
        }

        return new PageHeader($header->title, $header->intro, self::menuActions($header->actions), $header->breadcrumbs, $header->breadcrumbCollection, $header->tabs, $header->breadcrumbsMode, $header->back, $header->status, 'split');
    }

    /**
     * The same actions with the filled variant removed from the create / save ones, so none of them is the header's
     * primary button; destructive actions keep their variant (UiKit puts them last, in red).
     *
     * @param list<Action> $actions
     *
     * @return list<Action>
     */
    public static function menuActions(array $actions): array
    {
        $menu = [];
        foreach ($actions as $action) {
            $menu[] = $action->resolvedVariant() === 'primary' ? self::withVariant($action, 'secondary') : $action;
        }

        return $menu;
    }

    private static function withVariant(Action $action, string $variant): Action
    {
        return new Action(
            kind: $action->kind,
            label: $action->label,
            href: $action->href,
            route: $action->route,
            params: $action->params,
            method: $action->method,
            csrfToken: $action->csrfToken,
            confirm: $action->confirm,
            icon: $action->icon,
            variant: $variant,
            attributes: $action->attributes,
            form: $action->form,
            csrfField: $action->csrfField,
            modalId: $action->modalId,
            disabled: $action->disabled,
            visible: $action->visible,
            display: $action->display,
            confirmScope: $action->confirmScope,
            fallbackHref: $action->fallbackHref,
            dialogId: $action->dialogId,
            title: $action->title,
        );
    }
}
