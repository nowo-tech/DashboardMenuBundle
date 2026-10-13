<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DependencyInjection;

use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\Tests\Kernel\ProfilerToggleTestKernel;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Filesystem\Filesystem;

use function sys_get_temp_dir;

/**
 * Regression: the dev-only data collector must not break container compilation when the
 * profiler is disabled (no {@code profiler} service), and must still be registered when it is enabled.
 */
final class DataCollectorProfilerAvailabilityTest extends TestCase
{
    private ?ProfilerToggleTestKernel $kernel = null;

    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo_dashboard_menu_profiler_kernel');
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo_dashboard_menu_profiler_kernel');
        restore_exception_handler();
    }

    public function testDevContainerCompilesWithoutProfilerAndDropsCollector(): void
    {
        $this->kernel = new ProfilerToggleTestKernel('dev', false);
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        self::assertFalse($container->has('profiler'));
        self::assertNull($this->menuExtensionDataCollector());
    }

    public function testDevContainerKeepsCollectorWhenProfilerEnabled(): void
    {
        $this->kernel = new ProfilerToggleTestKernel('dev', true);
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        self::assertTrue($container->has('profiler'));
        self::assertInstanceOf(DashboardMenuDataCollector::class, $this->menuExtensionDataCollector());
    }

    public function testProdContainerCompilesWithoutProfiler(): void
    {
        $this->kernel = new ProfilerToggleTestKernel('prod', false);
        $this->kernel->boot();

        self::assertFalse($this->kernel->getContainer()->has('profiler'));
        self::assertNull($this->menuExtensionDataCollector());
    }

    private function menuExtensionDataCollector(): ?DashboardMenuDataCollector
    {
        self::assertNotNull($this->kernel);
        $extension = $this->kernel->getContainer()->get(MenuExtension::class);
        self::assertInstanceOf(MenuExtension::class, $extension);

        $value = (new ReflectionProperty(MenuExtension::class, 'dataCollector'))->getValue($extension);
        self::assertTrue($value === null || $value instanceof DashboardMenuDataCollector);

        return $value;
    }
}
