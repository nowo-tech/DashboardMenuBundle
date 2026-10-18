<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\View;

use InvalidArgumentException;
use Twig\Environment;

use function array_key_exists;
use function array_keys;
use function implode;
use function sprintf;
use function trigger_deprecation;

/**
 * Admin web UI of the dashboard on the UiKit page contract (REQ-UI-003): per-page templates
 * ({@code web_ui.templates.<page>}) and the layout the scaffolds extend ({@code web_ui.layout_template}; null =
 * {@code nowo_ui_layout.admin}).
 *
 * A 1.x layout (it renders the {@code nowo_dashboard_menu_content} block instead of the UiKit canonical blocks), set
 * through the deprecated {@code dashboard.layout_template} or kept as a host override of the removed
 * {@code @NowoDashboardMenuBundle/dashboard/layout.html.twig}, is wrapped in {@see self::LEGACY_LAYOUT_BRIDGE} for one
 * minor version.
 *
 * Immutable and request-agnostic (worker safe).
 */
final readonly class DashboardWebUi
{
    public const BUNDLE = 'NowoDashboardMenuBundle';

    /** 1.x default layout (Bootstrap / Tailwind / Foundation from CDNs): removed in 2.7, a host override still works (bridged). */
    public const LEGACY_LAYOUT = '@NowoDashboardMenuBundle/dashboard/layout.html.twig';

    /** Bridge around a 1.x layout that renders {@code nowo_dashboard_menu_content} (deprecated since 2.7). */
    public const LEGACY_LAYOUT_BRIDGE = '@NowoDashboardMenuBundle/dashboard/_legacy_layout.html.twig';

    /**
     * Page / fragment id => default template (override with {@code web_ui.templates.<page>}). The {@code *_partial}
     * templates are the dialog bodies the dashboard script loads with {@code ?_modal=1}.
     *
     * @var array<string, string>
     */
    public const TEMPLATES = [
        'index'              => '@NowoDashboardMenuBundle/dashboard/index.html.twig',
        'show'               => '@NowoDashboardMenuBundle/dashboard/show.html.twig',
        'show_items_reorder' => '@NowoDashboardMenuBundle/dashboard/show_items_reorder.html.twig',
        'menu_form'          => '@NowoDashboardMenuBundle/dashboard/menu_form.html.twig',
        'item_form'          => '@NowoDashboardMenuBundle/dashboard/item_form.html.twig',
        'copy_menu'          => '@NowoDashboardMenuBundle/dashboard/copy_menu.html.twig',
        'import'             => '@NowoDashboardMenuBundle/dashboard/import.html.twig',
        'menu_form_partial'  => '@NowoDashboardMenuBundle/dashboard/_menu_form_partial.html.twig',
        'item_form_partial'  => '@NowoDashboardMenuBundle/dashboard/_item_form_partial.html.twig',
        'item_form_live'     => '@NowoDashboardMenuBundle/dashboard/_item_form_live_partial.html.twig',
        'copy_menu_partial'  => '@NowoDashboardMenuBundle/dashboard/_copy_menu_partial.html.twig',
        'import_partial'     => '@NowoDashboardMenuBundle/dashboard/_import_partial.html.twig',
    ];

    /**
     * @param Environment|null $twig null without TwigBundle (no pages rendered: layout checks are skipped)
     * @param array<string, string> $templates web_ui.templates (page id => template)
     */
    public function __construct(
        private ?Environment $twig,
        private ?string $layoutTemplate = null,
        private array $templates = [],
    ) {
    }

    public function template(string $page): string
    {
        if (!array_key_exists($page, self::TEMPLATES)) {
            throw new InvalidArgumentException(sprintf('Unknown DashboardMenu page "%s" (%s).', $page, implode(', ', array_keys(self::TEMPLATES))));
        }

        return $this->templates[$page] ?? self::TEMPLATES[$page];
    }

    /**
     * The 1.x layout to wrap in the bridge, or null when the layout renders the UiKit canonical blocks (or there is
     * none: UiKit layout).
     */
    public function legacyLayout(): ?string
    {
        $layout = $this->configuredLayout();
        if ($layout === null || !$this->twig instanceof Environment) {
            return null;
        }

        return $this->twig->load($layout)->hasBlock('nowo_ui_content') ? null : $layout;
    }

    /**
     * The view-model layout: null (UiKit admin layout), the configured layout when it renders the canonical blocks,
     * or the legacy bridge around a 1.x layout.
     */
    public function layout(): ?string
    {
        $layout = $this->configuredLayout();
        if ($layout === null) {
            return null;
        }

        if ($this->legacyLayout() !== null) {
            trigger_deprecation('nowo-tech/dashboard-menu-bundle', '2.7', 'The layout "%s" renders the 1.x block "nowo_dashboard_menu_content" instead of the UiKit canonical blocks (nowo_ui_content…); it is wrapped in "%s". Point nowo_ui_kit.layouts.admin (or nowo_dashboard_menu.web_ui.layout_template) to a layout that renders the canonical blocks (see docs/UPGRADING.md).', $layout, self::LEGACY_LAYOUT_BRIDGE);

            return self::LEGACY_LAYOUT_BRIDGE;
        }

        return $layout;
    }

    /**
     * Twig context shared by every page: the 1.x layout the bridge extends (null when not bridged).
     *
     * @return array{nowo_dashboard_menu_legacy_layout: string|null}
     */
    public function context(): array
    {
        return ['nowo_dashboard_menu_legacy_layout' => $this->legacyLayout()];
    }

    /**
     * web_ui.layout_template, else a host override of the removed 1.x default layout (it still exists only when the
     * host ships templates/bundles/NowoDashboardMenuBundle/dashboard/layout.html.twig).
     */
    private function configuredLayout(): ?string
    {
        if ($this->layoutTemplate !== null) {
            return $this->layoutTemplate;
        }

        return $this->twig instanceof Environment && $this->twig->getLoader()->exists(self::LEGACY_LAYOUT) ? self::LEGACY_LAYOUT : null;
    }
}
