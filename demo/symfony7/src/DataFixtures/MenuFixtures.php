<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Controller\HomeController;
use App\Controller\SystemController;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;

class MenuFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $this->createSidebar($manager);
        $this->createSidebarVariant($manager, 'Partner+Operator', ['partnerId' => 1, 'operatorId' => 1]);
        $this->createSidebarVariant($manager, 'Partner', ['partnerId' => 1]);
        $this->createDropdown($manager);
        $this->createFooter($manager);
        $this->createAside($manager);
        $this->createLocaleSwitcher($manager);
        $manager->flush();
    }

    private function createSidebar(ObjectManager $manager): void
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $menu->setName('Sidebar');
        $manager->persist($menu);

        $pos = 0;
        $home = $this->addItem($manager, $menu, null, 'Home', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, [], $pos++, null, ['en' => 'Home', 'es' => 'Inicio']);
        $dashboard = $this->addItem($manager, $menu, null, 'Dashboard', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'dashboard'], $pos++, null, ['en' => 'Dashboard', 'es' => 'Panel']);

        $this->addItem($manager, $menu, $dashboard, 'Overview', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'overview'], 0);
        $this->addItem($manager, $menu, $dashboard, 'Analytics', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'analytics'], 1);
        $this->addItem($manager, $menu, $dashboard, 'Reports', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'reports'], 2);

        $this->addItem($manager, $menu, null, 'Navigation', MenuItem::ITEM_TYPE_SECTION, null, null, $pos++, null, ['en' => 'Navigation', 'es' => 'Navegación']);
        $this->addItem($manager, $menu, null, null, MenuItem::ITEM_TYPE_DIVIDER, null, null, $pos++);

        // 4 niveles: Pages → Settings → Account → Profile, Password
        $pages = $this->addItem($manager, $menu, null, 'Pages', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'pages'], $pos++);
        $this->addItem($manager, $menu, $pages, 'Profile', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'profile'], 0);
        $settings = $this->addItem($manager, $menu, $pages, 'Settings', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'settings'], 1);
        $account = $this->addItem($manager, $menu, $settings, 'Account', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'account'], 0);
        $this->addItem($manager, $menu, $account, 'Profile', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'profile'], 0);
        $this->addItem($manager, $menu, $account, 'Password', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'password'], 1);
        $this->addItem($manager, $menu, $pages, 'Preferences', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'preferences'], 2);

        $this->addItem($manager, $menu, null, 'Resources', MenuItem::ITEM_TYPE_SECTION, null, null, $pos++, null, ['en' => 'Resources', 'es' => 'Recursos']);
        $this->addItem($manager, $menu, null, null, MenuItem::ITEM_TYPE_DIVIDER, null, null, $pos++);

        // 4 niveles: Documents → Doc A → A.1 → A.1.1, A.1.2; Doc B → B.1 → B.1.1
        $documents = $this->addItem($manager, $menu, null, 'Documents', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'documents'], $pos++);
        $docA = $this->addItem($manager, $menu, $documents, 'Doc A', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'doc_a'], 0);
        $docA1 = $this->addItem($manager, $menu, $docA, 'A.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'a1'], 0);
        $this->addItem($manager, $menu, $docA1, 'A.1.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'a11'], 0);
        $this->addItem($manager, $menu, $docA1, 'A.1.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'a12'], 1);
        $this->addItem($manager, $menu, $docA, 'A.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'a2'], 1);
        $docB = $this->addItem($manager, $menu, $documents, 'Doc B', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'doc_b'], 1);
        $docB1 = $this->addItem($manager, $menu, $docB, 'B.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'b1'], 0);
        $this->addItem($manager, $menu, $docB1, 'B.1.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'b11'], 0);
        $this->addItem($manager, $menu, $docB1, 'B.1.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'b12'], 1);
        $this->addItem($manager, $menu, $docB, 'B.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'b2'], 1);

        $api = $this->addItem($manager, $menu, null, 'Menu API (JSON)', MenuItem::ITEM_TYPE_LINK, 'nowo_dashboard_menu_api', ['code' => 'sidebar'], $pos++);
        $this->addItem($manager, $menu, $api, 'Sidebar tree', MenuItem::ITEM_TYPE_LINK, 'nowo_dashboard_menu_api', ['code' => 'sidebar'], 0);
        $this->addItem($manager, $menu, $api, 'Dropdown tree', MenuItem::ITEM_TYPE_LINK, 'nowo_dashboard_menu_api', ['code' => 'dropdown'], 1);

        $this->addItem($manager, $menu, null, 'External', MenuItem::ITEM_TYPE_SECTION, null, null, $pos++, null, ['en' => 'External', 'es' => 'Enlaces externos']);
        $this->addItem($manager, $menu, null, 'Symfony', MenuItem::ITEM_TYPE_LINK, null, null, $pos++, 'https://symfony.com');
        $this->addItem($manager, $menu, null, 'Documentation', MenuItem::ITEM_TYPE_LINK, null, null, $pos++, 'https://symfony.com/doc');
    }

    /** Same code 'sidebar' with context (partnerId/operatorId) for resolution demo. */
    private function createSidebarVariant(ObjectManager $manager, string $label, array $context): void
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $menu->setName('Sidebar (' . $label . ')');
        $menu->setContext($context);
        $manager->persist($menu);

        $pos = 0;
        $this->addItem($manager, $menu, null, $label . ' menu', MenuItem::ITEM_TYPE_SECTION, null, null, $pos++);
        $this->addItem($manager, $menu, null, 'Home', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, [], $pos++, null, ['en' => 'Home', 'es' => 'Inicio']);
        $this->addItem($manager, $menu, null, $label . ' feature', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => strtolower($label)], $pos++);
        $this->addItem($manager, $menu, null, 'Settings', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'settings'], $pos++);
    }

    private function createLocaleSwitcher(ObjectManager $manager): void
    {
        $menu = new Menu();
        $menu->setCode('locale_switcher');
        $menu->setName('Language');
        $menu->setClassMenu('nav');
        $menu->setClassItem('nav-item');
        $menu->setClassLink('nav-link');
        $manager->persist($menu);

        $this->addItem($manager, $menu, null, 'English', MenuItem::ITEM_TYPE_LINK, SystemController::APP_SWITCH_LOCALE_ROUTE, ['_locale' => 'en'], 0, null, ['en' => 'English', 'es' => 'Inglés', 'fr' => 'Anglais']);
        $this->addItem($manager, $menu, null, 'Español', MenuItem::ITEM_TYPE_LINK, SystemController::APP_SWITCH_LOCALE_ROUTE, ['_locale' => 'es'], 1, null, ['en' => 'Spanish', 'es' => 'Español', 'fr' => 'Espagnol']);
        $this->addItem($manager, $menu, null, 'Français', MenuItem::ITEM_TYPE_LINK, SystemController::APP_SWITCH_LOCALE_ROUTE, ['_locale' => 'fr'], 2, null, ['en' => 'French', 'es' => 'Francés', 'fr' => 'Français']);
    }

    private function createDropdown(ObjectManager $manager): void
    {
        $menu = new Menu();
        $menu->setCode('dropdown');
        $menu->setName('Dropdown');
        $manager->persist($menu);

        $pos = 0;
        $this->addItem($manager, $menu, null, 'My account', MenuItem::ITEM_TYPE_SECTION, null, null, $pos++);
        $this->addItem($manager, $menu, null, 'Profile', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'profile'], $pos++);
        $this->addItem($manager, $menu, null, 'Settings', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'settings'], $pos++);
        $this->addItem($manager, $menu, null, null, MenuItem::ITEM_TYPE_DIVIDER, null, null, $pos++);
        $this->addItem($manager, $menu, null, 'Help', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'help'], $pos++);
        $this->addItem($manager, $menu, null, 'Logout', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'logout'], $pos++);
    }

    private function createFooter(ObjectManager $manager): void
    {
        $menu = new Menu();
        $menu->setCode('footer');
        $menu->setName('Footer');
        $manager->persist($menu);

        $pos = 0;
        $this->addItem($manager, $menu, null, 'About', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'about'], $pos++);
        $this->addItem($manager, $menu, null, 'Privacy', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'privacy'], $pos++);
        $this->addItem($manager, $menu, null, 'Terms', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'terms'], $pos++);
        $this->addItem($manager, $menu, null, null, MenuItem::ITEM_TYPE_DIVIDER, null, null, $pos++);
        $this->addItem($manager, $menu, null, 'Contact', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'contact'], $pos++);
        $this->addItem($manager, $menu, null, 'Support', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'support'], $pos++);
        $this->addItem($manager, $menu, null, 'Status', MenuItem::ITEM_TYPE_LINK, null, null, $pos++, 'https://status.example.com');
    }

    private function createAside(ObjectManager $manager): void
    {
        $menu = new Menu();
        $menu->setCode('aside');
        $menu->setName('Aside');
        $manager->persist($menu);

        $pos = 0;
        $this->addItem($manager, $menu, null, 'Filters', MenuItem::ITEM_TYPE_SECTION, null, null, $pos++);
        $this->addItem($manager, $menu, null, 'All', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'all'], $pos++);
        $this->addItem($manager, $menu, null, 'Recent', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'recent'], $pos++);
        $this->addItem($manager, $menu, null, null, MenuItem::ITEM_TYPE_DIVIDER, null, null, $pos++);

        // 4 niveles: Categories → Category A → A1 → A1.1, A1.2; Category B → B1 → B1.1, B1.2
        $categories = $this->addItem($manager, $menu, null, 'Categories', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'categories'], $pos++);
        $catA = $this->addItem($manager, $menu, $categories, 'Category A', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'cat_a'], 0);
        $catA1 = $this->addItem($manager, $menu, $catA, 'A1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'a1'], 0);
        $this->addItem($manager, $menu, $catA1, 'A1.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'a11'], 0);
        $this->addItem($manager, $menu, $catA1, 'A1.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'a12'], 1);
        $this->addItem($manager, $menu, $catA, 'A2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'a2'], 1);
        $catB = $this->addItem($manager, $menu, $categories, 'Category B', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'cat_b'], 1);
        $catB1 = $this->addItem($manager, $menu, $catB, 'B1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'b1'], 0);
        $this->addItem($manager, $menu, $catB1, 'B1.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'b11'], 0);
        $this->addItem($manager, $menu, $catB1, 'B1.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'b12'], 1);
        $this->addItem($manager, $menu, $categories, 'Item C', MenuItem::ITEM_TYPE_LINK, HomeController::APP_PAGE_ROUTE, ['page' => 'item_c'], 2);

        $this->addItem($manager, $menu, null, 'Quick links', MenuItem::ITEM_TYPE_SECTION, null, null, $pos++);
        $this->addItem($manager, $menu, null, 'API Sidebar', MenuItem::ITEM_TYPE_LINK, 'nowo_dashboard_menu_api', ['code' => 'sidebar'], $pos++);
        $this->addItem($manager, $menu, null, 'API Footer', MenuItem::ITEM_TYPE_LINK, 'nowo_dashboard_menu_api', ['code' => 'footer'], $pos++);
    }

    private function addItem(
        ObjectManager $manager,
        Menu $menu,
        ?MenuItem $parent,
        ?string $label,
        string $itemType,
        ?string $routeName,
        ?array $routeParams,
        int $position,
        ?string $externalUrl = null,
        array $translations = [],
    ): MenuItem {
        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setParent($parent);
        $item->setLabel($label ?? '');
        if ($translations !== []) {
            $item->setTranslations($translations);
        }
        $item->setItemType($itemType);
        $item->setPosition($position);
        if ($itemType === MenuItem::ITEM_TYPE_LINK) {
            if ($externalUrl !== null) {
                $item->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);
                $item->setExternalUrl($externalUrl);
            } else {
                $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
                $item->setRouteName($routeName);
                $item->setRouteParams($routeParams ?? []);
            }
        }
        $menu->addItem($item);
        $manager->persist($item);

        return $item;
    }
}
