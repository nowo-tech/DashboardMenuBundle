<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;

class MenuFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $menu->setName('Sidebar');
        $manager->persist($menu);

        $home = new MenuItem();
        $home->setMenu($menu);
        $home->setLabel('Home');
        $home->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $home->setRouteName('app_home');
        $home->setRouteParams([]);
        $home->setPosition(0);
        $menu->addItem($home);
        $manager->persist($home);

        $api = new MenuItem();
        $api->setMenu($menu);
        $api->setParent($home);
        $api->setLabel('Menu API (JSON)');
        $api->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $api->setRouteName('nowo_dashboard_menu_api');
        $api->setRouteParams(['code' => 'sidebar']);
        $api->setPosition(0);
        $manager->persist($api);

        $manager->flush();
    }
}
