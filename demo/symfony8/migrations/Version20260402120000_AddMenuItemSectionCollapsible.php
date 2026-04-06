<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-section override for collapsible children (nullable tri-state).
 */
final class Version20260402120000_AddMenuItemSectionCollapsible extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add section_collapsible to dashboard_menu_item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu_item ADD section_collapsible TINYINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu_item DROP section_collapsible');
    }
}
