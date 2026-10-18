<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\DependencyInjection;

use Nowo\DashboardMenuBundle\DependencyInjection\LegacyThemeBridge;
use Nowo\DashboardMenuBundle\Service\DashboardUiConfig;
use PHPUnit\Framework\TestCase;

final class LegacyThemeBridgeTest extends TestCase
{
    public function testNothingToBridgeWithDefaults(): void
    {
        self::assertSame([], LegacyThemeBridge::themeException([]));
        self::assertSame([], LegacyThemeBridge::themeException([
            'classes'  => ['button_primary' => null, 'list' => 'list-group'],
            'partials' => DashboardUiConfig::DEFAULT_PARTIALS,
        ]));
    }

    public function testClassesBecomeSlotExceptionsWithoutFrameworkDefaults(): void
    {
        $theme = LegacyThemeBridge::themeException([
            'classes' => [
                'button_primary'    => 'btn btn-dark',
                'row_action'        => ' btn-sm ',
                'row_action_danger' => '',
                'button_ghost'      => 'btn-ghost',
                'sortable_panel'    => 'card shadow-sm',
            ],
            'partials' => ['pagination' => 'admin/_pagination.html.twig', 'modal' => 'admin/_modal.html.twig'],
        ]);

        self::assertSame([
            'btn_primary'   => ['value' => 'btn btn-dark', 'framework_defaults' => false],
            'action'        => ['value' => 'btn-sm', 'framework_defaults' => false],
            'action_danger' => ['value' => '', 'framework_defaults' => false],
            'card'          => ['value' => 'card shadow-sm', 'framework_defaults' => false],
        ], $theme['classes'] ?? null);
        self::assertSame(['pagination' => 'admin/_pagination.html.twig'], $theme['partials'] ?? null);
    }
}
