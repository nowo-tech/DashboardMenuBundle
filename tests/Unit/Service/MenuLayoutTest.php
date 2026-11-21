<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuLayout;
use Nowo\DashboardMenuBundle\Tests\Unit\Templates\DashboardTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class MenuLayoutTest extends TestCase
{
    public function testEmptyLayoutIsTheIdentity(): void
    {
        $layout = new MenuLayout();
        $tree   = [$this->node($this->item(1, 'Home'))];

        self::assertSame($tree, $layout->main($tree, 'sidebar'));
        self::assertSame([], $layout->footer($tree, 'sidebar'));
        self::assertNull($layout->section($tree[0]['item'], 'sidebar'));
    }

    public function testFooterSelectorsMatchByIdRouteAndLabelAndSplitTheTree(): void
    {
        $layout = new MenuLayout(['sidebar' => ['footer' => [
            ['id' => 2],
            ['route' => 'app_help'],
            ['label' => 'Contacto'],
            ['route' => 'app_help', 'label' => 'Never'],
        ]]]);
        $home    = $this->node($this->item(1, 'Home'));
        $byId    = $this->node($this->item(2, 'Two'));
        $byRoute = $this->node($this->item(3, 'Help', 'link', 'app_help'));
        $byLabel = $this->node($this->item(4, 'Contact', 'link', null, ['es' => 'Contacto']));
        $tree    = [$home, $byId, $byRoute, $byLabel, ['children' => []]];

        self::assertSame([$home, ['children' => []]], $layout->main($tree, 'sidebar'));
        self::assertSame([$byId, $byRoute, $byLabel], $layout->footer($tree, 'sidebar'));
        self::assertSame($tree, $layout->main($tree, 'other'));
        self::assertSame([], $layout->footer($tree, 'other'));
    }

    public function testSectionOptions(): void
    {
        $layout = new MenuLayout(['sidebar' => [
            'storage_key' => 'k',
            'sections'    => [
                ['label' => 'Off', 'collapsible' => false],
                ['label' => 'Tools', 'collapsed' => true],
                ['id' => 9],
            ],
        ], 'bare' => ['sections' => [['id' => 9]]]]);

        self::assertSame(['collapsible' => true, 'collapsed' => true, 'storage_key' => 'k', 'key' => 'sidebar:5'], $layout->section($this->item(5, 'Tools', 'section'), 'sidebar'));
        self::assertSame(['collapsible' => true, 'collapsed' => false, 'storage_key' => 'k', 'key' => 'sidebar:9'], $layout->section($this->item(9, 'Anything', 'section'), 'sidebar'));
        self::assertSame(MenuLayout::DEFAULT_STORAGE_KEY, $layout->section($this->item(9, 'X', 'section'), 'bare')['storage_key'] ?? null);
        // Opted out, unlisted, wrong type, not an item, unknown menu.
        self::assertNull($layout->section($this->item(6, 'Off', 'section'), 'sidebar'));
        self::assertNull($layout->section($this->item(7, 'Other', 'section'), 'sidebar'));
        self::assertNull($layout->section($this->item(5, 'Tools', 'link'), 'sidebar'));
        self::assertNull($layout->section('Tools', 'sidebar'));
        self::assertNull($layout->section($this->item(5, 'Tools', 'section'), 'nope'));
    }

    /**
     * @param array<string, string>|null $translations
     */
    private function item(int $id, string $label, string $type = 'link', ?string $route = null, ?array $translations = null): MenuItem
    {
        $item = DashboardTemplateRenderer::item($id, $label, true, $type);
        $item->setRouteName($route);
        $item->setTranslations($translations);

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function node(MenuItem $item): array
    {
        return ['item' => $item, 'children' => []];
    }
}
