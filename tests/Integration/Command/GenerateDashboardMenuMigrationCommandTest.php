<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Command\GenerateDashboardMenuMigrationCommand;
use Nowo\DashboardMenuBundle\Tests\Kernel\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class GenerateDashboardMenuMigrationCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    public function testExecuteWithDumpDoesNotWriteFile(): void
    {
        $command = new GenerateDashboardMenuMigrationCommand($this->entityManager, 'default', '');
        $tester  = new CommandTester($command);

        $status = $tester->execute([
            '--dump' => true,
        ]);

        self::assertSame(0, $status);
        $output = $tester->getDisplay();
        // Output formatting includes Symfony "NOTE" decorations and may split
        // lines with `! [NOTE] ...` across multiple rows depending on console styles.
        self::assertStringContainsString('Run with the same', $output);
        // Depending on Symfony console wrapping, "connection as in" can be split
        // across lines/decorated blocks; match both terms loosely in order.
        self::assertMatchesRegularExpression('/(?s)connection.*as in/', $output);
        // Symfony may wrap the connection note, splitting the text:
        // e.g. "nowo_dashboard_menu.d\n! ... octrine.connection"
        self::assertStringContainsString('nowo_dashboard_menu', $output);
        self::assertStringContainsString('connection', $output);
        // The console output may insert line breaks and decoration between the parts
        // of "nowo_dashboard_menu.doctrine.connection", so match it loosely.
        self::assertMatchesRegularExpression('/(?s)nowo_dashboard_menu\..*connection/', $output);
    }

    public function testExecuteWritesMigrationFileWhenNotDumping(): void
    {
        $tmpDir = sys_get_temp_dir() . '/dmb_migration_' . uniqid('', true);

        $command = new GenerateDashboardMenuMigrationCommand($this->entityManager, 'default', '');
        $tester  = new CommandTester($command);

        $status = $tester->execute([
            '--path'      => $tmpDir,
            '--namespace' => 'DoctrineMigrations',
        ]);

        self::assertSame(0, $status);

        $files = glob($tmpDir . '/*.php') ?: [];
        self::assertNotEmpty($files, 'Expected at least one generated migration file');

        $content = file_get_contents($files[0]);
        self::assertIsString($content);
        self::assertStringContainsString('Dashboard Menu tables (menu + menu_item).', $content);
    }

    public function testExecuteWithUpdateOptionGeneratesAlterAndUsesRelativePathAndNonDefaultConnection(): void
    {
        $conn = $this->entityManager->getConnection();

        // Minimal schema so update mode can introspect and detect the missing column.
        // Intentionally omit `class_section_label` to force the ALTER branch.
        $conn->executeStatement('DROP TABLE IF EXISTS dashboard_menu');
        $conn->executeStatement(
            'CREATE TABLE dashboard_menu (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code VARCHAR(64) NOT NULL,
                attributes_key VARCHAR(512) NOT NULL
            )',
        );

        $tmpBaseDir      = sys_get_temp_dir();
        $relativeDirName = 'dmb_migration_update_' . uniqid('', true);
        $targetDir       = $tmpBaseDir . '/' . $relativeDirName;
        $filesystem      = new Filesystem();
        $oldCwd          = getcwd();

        $command = new GenerateDashboardMenuMigrationCommand($this->entityManager, 'other', '');
        $tester  = new CommandTester($command);

        try {
            // Ensure --path is relative so GenerateDashboardMenuMigrationCommand hits
            // the "!isAbsolutePath($path)" branch, but still write into temp (not into the repo).
            chdir($tmpBaseDir);
            $status = $tester->execute([
                '--path'      => $relativeDirName,
                '--namespace' => 'DoctrineMigrations',
                '--update'    => true,
            ]);

            self::assertSame(0, $status);

            $output = $tester->getDisplay();
            self::assertStringContainsString('--conn=other', $output);

            $files = glob($targetDir . '/*.php') ?: [];
            self::assertNotEmpty($files, 'Expected a generated migration file');

            $content = file_get_contents($files[0]);
            self::assertStringContainsString('ADD class_section_label', $content);
        } finally {
            // Avoid leaving dmb_migration_* artefacts in the repo/workspace.
            if (is_dir($targetDir)) {
                $filesystem->remove($targetDir);
            }
            if ($oldCwd !== false) {
                chdir($oldCwd);
            }
        }
    }
}
