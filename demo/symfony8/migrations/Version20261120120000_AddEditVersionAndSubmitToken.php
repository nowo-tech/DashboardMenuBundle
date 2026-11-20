<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 2.24 (REQ-RUNTIME-003 rule 4): optimistic edit version of menus and items, and the unique token of the form that
 * created an item (a form posted twice creates one item).
 */
final class Version20261120120000_AddEditVersionAndSubmitToken extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add edit_version to dashboard_menu and dashboard_menu_item, submit_token (unique) to dashboard_menu_item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu ADD edit_version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE dashboard_menu_item ADD edit_version INT DEFAULT 1 NOT NULL, ADD submit_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_menu_item_submit_token ON dashboard_menu_item (submit_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_menu_item_submit_token ON dashboard_menu_item');
        $this->addSql('ALTER TABLE dashboard_menu_item DROP edit_version, DROP submit_token');
        $this->addSql('ALTER TABLE dashboard_menu DROP edit_version');
    }
}
