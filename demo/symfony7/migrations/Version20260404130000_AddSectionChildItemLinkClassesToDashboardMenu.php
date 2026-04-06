<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optional CSS classes for <li> and <a> on direct children of section items.
 */
final class Version20260404130000_AddSectionChildItemLinkClassesToDashboardMenu extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add class_section_child_item and class_section_child_link to dashboard_menu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu ADD class_section_child_item VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE dashboard_menu ADD class_section_child_link VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu DROP class_section_child_item');
        $this->addSql('ALTER TABLE dashboard_menu DROP class_section_child_link');
    }
}
