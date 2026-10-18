<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Kernel;

use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;

/**
 * Host env vars holding a locale list, as %env(json:…)% and %env(csv:…)% read them at runtime.
 */
final class LocaleEnvVarLoader implements EnvVarLoaderInterface
{
    public const JSON = 'NOWO_DASHBOARD_MENU_TEST_LOCALES_JSON';

    public const CSV = 'NOWO_DASHBOARD_MENU_TEST_LOCALES_CSV';

    public function loadEnvVars(): array
    {
        return [self::JSON => '["pt","en"]', self::CSV => 'it,en'];
    }
}
