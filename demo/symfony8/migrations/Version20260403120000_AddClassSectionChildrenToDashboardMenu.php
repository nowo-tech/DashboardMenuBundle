<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Optional CSS class for <ul> under section items (flat list vs nested children class).
 */
final class Version20260403120000_AddClassSectionChildrenToDashboardMenu extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add class_section_children to dashboard_menu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu ADD class_section_children VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu DROP class_section_children');
    }
}
