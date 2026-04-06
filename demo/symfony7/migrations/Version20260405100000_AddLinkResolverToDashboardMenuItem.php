<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Item type "service": optional service id for MenuLinkResolverInterface (href at runtime).
 */
final class Version20260405100000_AddLinkResolverToDashboardMenuItem extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add link_resolver to dashboard_menu_item (MenuItem::linkResolver)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu_item ADD link_resolver VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_menu_item DROP link_resolver');
    }
}
