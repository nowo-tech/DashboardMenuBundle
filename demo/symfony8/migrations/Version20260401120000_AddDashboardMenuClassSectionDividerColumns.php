<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds class_section and class_divider to dashboard_menu (aligned with Menu entity).
 * The initial create migration predates these columns; existing installs need this ALTER.
 */
final class Version20260401120000_AddDashboardMenuClassSectionDividerColumns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add class_section and class_divider columns to dashboard_menu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu ADD class_section VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE dashboard_menu ADD class_divider VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu DROP class_section');
        $this->addSql('ALTER TABLE dashboard_menu DROP class_divider');
    }
}
