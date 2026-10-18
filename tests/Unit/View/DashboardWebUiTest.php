<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\View;

use InvalidArgumentException;
use Nowo\DashboardMenuBundle\View\DashboardWebUi;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class DashboardWebUiTest extends TestCase
{
    public function testTemplatesDefaultToTheBundlePagesAndAcceptOverrides(): void
    {
        $webUi = new DashboardWebUi(null, null, ['index' => 'admin/menus.html.twig']);

        self::assertSame('admin/menus.html.twig', $webUi->template('index'));
        self::assertSame(DashboardWebUi::TEMPLATES['show'], $webUi->template('show'));
    }

    public function testUnknownPageIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown DashboardMenu page "nope"');

        (new DashboardWebUi(null))->template('nope');
    }

    public function testNoLayoutMeansTheUiKitLayout(): void
    {
        $webUi = new DashboardWebUi(new Environment(new ArrayLoader([])));

        self::assertNull($webUi->layout());
        self::assertNull($webUi->legacyLayout());
        self::assertSame(['nowo_dashboard_menu_legacy_layout' => null], $webUi->context());
    }

    public function testWithoutTwigNothingIsResolved(): void
    {
        $webUi = new DashboardWebUi(null, 'layouts/admin.html.twig');

        self::assertNull($webUi->legacyLayout());
        self::assertSame('layouts/admin.html.twig', $webUi->layout());
    }

    public function testCanonicalLayoutIsUsedAsIs(): void
    {
        $twig  = new Environment(new ArrayLoader(['layouts/admin.html.twig' => '{% block nowo_ui_content %}{% endblock %}']));
        $webUi = new DashboardWebUi($twig, 'layouts/admin.html.twig');

        self::assertSame('layouts/admin.html.twig', $webUi->layout());
        self::assertNull($webUi->legacyLayout());
    }

    #[IgnoreDeprecations]
    public function testLegacyLayoutIsBridged(): void
    {
        $twig  = new Environment(new ArrayLoader(['legacy.html.twig' => '{% block nowo_dashboard_menu_content %}{% endblock %}']));
        $webUi = new DashboardWebUi($twig, 'legacy.html.twig');

        self::assertSame('legacy.html.twig', $webUi->legacyLayout());
        self::assertSame(['nowo_dashboard_menu_legacy_layout' => 'legacy.html.twig'], $webUi->context());

        $this->expectUserDeprecationMessageMatches('/renders the 1\.x block "nowo_dashboard_menu_content"/');
        self::assertSame(DashboardWebUi::LEGACY_LAYOUT_BRIDGE, $webUi->layout());
    }

    public function testHostOverrideOfTheRemovedDefaultLayoutIsStillUsed(): void
    {
        $twig  = new Environment(new ArrayLoader([DashboardWebUi::LEGACY_LAYOUT => '{% block nowo_dashboard_menu_content %}{% endblock %}']));
        $webUi = new DashboardWebUi($twig);

        self::assertSame(DashboardWebUi::LEGACY_LAYOUT, $webUi->legacyLayout());
    }
}
