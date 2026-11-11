<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Event;

use Nowo\DashboardMenuBundle\Model\MenuNodeView;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after {@see MenuTreeLoader::loadTreeView()} built a menu tree as {@see MenuNodeView}s (2.18, architecture
 * bar rule 4): listeners may add domain-driven entries (e.g. the user's projects) or remove nodes through
 * {@see self::setNodes()} without a `service` item and without the bundle's entities.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuTreeBuiltEvent extends Event
{
    /**
     * @param list<MenuNodeView> $nodes
     */
    public function __construct(
        public readonly string $menuCode,
        public readonly string $locale,
        private array $nodes,
    ) {
    }

    /**
     * @return list<MenuNodeView>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @param list<MenuNodeView> $nodes
     */
    public function setNodes(array $nodes): void
    {
        // @igor-ignore - Request-scoped event object; not a shared worker service.
        $this->nodes = $nodes;
    }
}
