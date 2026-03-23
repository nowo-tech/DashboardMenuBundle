<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Controller\HomeController;
use App\Controller\SystemController;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;

/**
 * Loads sample menus with at least 3 levels (root → children → grandchildren) for the demo.
 * Sidebar and Aside have collapsible items (nested_collapsible in config).
 */
class MenuFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $menus = [
            'sidebar'                  => $this->createSidebar($manager),
            'sidebar_partner_operator' => $this->createSidebarVariant($manager, 'Partner+Operator', ['partnerId' => 1, 'operatorId' => 1]),
            'sidebar_partner'          => $this->createSidebarVariant($manager, 'Partner', ['partnerId' => 1]),
            'aside'                    => $this->createAside($manager),
            'footer'                   => $this->createFooter($manager),
            'dropdown'                 => $this->createDropdown($manager),
            'locale_switcher'          => $this->createLocaleSwitcher($manager),
        ];

        foreach ($menus as $code => $menu) {
            $manager->persist($menu);
        }
        $manager->flush();

        foreach ($menus as $code => $menu) {
            $this->addItems($manager, $menu, $code);
        }
        $manager->flush();
    }

    private function createSidebar(ObjectManager $manager): Menu
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $menu->setName('Sidebar');
        $menu->setClassMenu('nav flex-column');
        $menu->setClassItem('nav-item');
        $menu->setClassLink('nav-link py-0 d-flex align-items-center menu-title text-truncate');
        $menu->setClassChildren('nav flex-column ms-2');
        $menu->setClassCurrent('active');
        $menu->setClassBranchExpanded('active-branch');
        $menu->setClassHasChildren('has-sub');
        $menu->setClassExpanded('expanded');
        $menu->setClassCollapsed('collapsed');
        $menu->setCollapsible(true);
        $menu->setCollapsibleExpanded(true);
        $menu->setNestedCollapsible(true);
        $menu->setPermissionChecker('App\\Service\\CustomDemoPermissionChecker');

        // No context = fallback when resolving by code with context sets
        return $menu;
    }

    /** Same code 'sidebar' with context (partnerId/operatorId): first or second match; fallback = menu with no context. */
    private function createSidebarVariant(ObjectManager $manager, string $key, array $context): Menu
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $menu->setName('Sidebar (' . $key . ')');
        $menu->setClassMenu('nav flex-column');
        $menu->setClassItem('nav-item');
        $menu->setClassLink('nav-link py-0 d-flex align-items-center menu-title text-truncate');
        $menu->setClassChildren('nav flex-column ms-2');
        $menu->setClassCurrent('active');
        $menu->setClassBranchExpanded('active-branch');
        $menu->setClassHasChildren('has-sub');
        $menu->setClassExpanded('expanded');
        $menu->setClassCollapsed('collapsed');
        $menu->setCollapsible(true);
        $menu->setCollapsibleExpanded(true);
        $menu->setNestedCollapsible(true);
        $menu->setPermissionChecker('App\\Service\\CustomDemoPermissionChecker');
        $menu->setContext($context);

        return $menu;
    }

    private function createAside(ObjectManager $manager): Menu
    {
        $menu = new Menu();
        $menu->setCode('aside');
        $menu->setName('Aside');
        $menu->setClassMenu('nav flex-column');
        $menu->setClassItem('nav-item');
        $menu->setClassLink('nav-link py-0 d-flex align-items-center menu-title text-truncate');
        $menu->setClassChildren('nav flex-column ms-2');
        $menu->setClassCurrent('active');
        $menu->setClassBranchExpanded('active-branch');
        $menu->setClassHasChildren('has-sub');
        $menu->setClassExpanded('expanded');
        $menu->setClassCollapsed('collapsed');
        $menu->setCollapsible(true);
        $menu->setCollapsibleExpanded(true);
        $menu->setNestedCollapsible(true);
        $menu->setPermissionChecker('App\\Service\\DemoMenuPermissionChecker');

        return $menu;
    }

    private function createFooter(ObjectManager $manager): Menu
    {
        $menu = new Menu();
        $menu->setCode('footer');
        $menu->setName('Footer');
        $menu->setClassMenu('nav flex-wrap gap-1');
        $menu->setClassItem('nav-item');
        $menu->setClassLink('nav-link link-secondary');
        $menu->setClassChildren('nav flex-column');
        $menu->setClassCurrent('active');
        $menu->setClassBranchExpanded('active-branch');
        $menu->setPermissionChecker('App\\Service\\DemoMenuPermissionChecker');

        return $menu;
    }

    private function createDropdown(ObjectManager $manager): Menu
    {
        $menu = new Menu();
        $menu->setCode('dropdown');
        $menu->setName('User menu');
        $menu->setClassMenu('dropdown-menu dropdown-menu-end');
        $menu->setClassItem('');
        $menu->setClassLink('dropdown-item');
        $menu->setClassChildren('dropdown-menu');
        $menu->setClassCurrent('active');
        $menu->setClassBranchExpanded('active-branch');
        $menu->setPermissionChecker('App\\Service\\DemoMenuPermissionChecker');

        return $menu;
    }

    private function createLocaleSwitcher(ObjectManager $manager): Menu
    {
        $menu = new Menu();
        $menu->setCode('locale_switcher');
        $menu->setName('Language');
        $menu->setClassMenu('nav');
        $menu->setClassItem('nav-item');
        $menu->setClassLink('nav-link');
        $menu->setClassChildren('');
        $menu->setClassCurrent('active');
        $menu->setClassBranchExpanded('active-branch');
        $menu->setPermissionChecker('App\\Service\\DemoMenuPermissionChecker');

        return $menu;
    }

    private function addItems(ObjectManager $manager, Menu $menu, string $code): void
    {
        if ($code === 'sidebar') {
            $this->addSidebarItems($manager, $menu);
        } elseif ($code === 'sidebar_partner_operator') {
            $this->addSidebarVariantItems($manager, $menu, 'Partner+Operator');
        } elseif ($code === 'sidebar_partner') {
            $this->addSidebarVariantItems($manager, $menu, 'Partner');
        } elseif ($code === 'aside') {
            $this->addAsideItems($manager, $menu);
        } elseif ($code === 'footer') {
            $this->addFooterItems($manager, $menu);
        } elseif ($code === 'dropdown') {
            $this->addDropdownItems($manager, $menu);
        } elseif ($code === 'locale_switcher') {
            $this->addLocaleSwitcherItems($manager, $menu);
        }
    }

    private function addSidebarItems(ObjectManager $manager, Menu $menu): void
    {
        $navSection = $this->item($menu, null, 0, 'Navigation', MenuItem::ITEM_TYPE_SECTION, null, [], MenuItem::LINK_TYPE_ROUTE, null, null, null, ['en' => 'Navigation', 'es' => 'Navegación']);
        $manager->persist($navSection);

        $home = $this->item($menu, null, 1, 'Home', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, [], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:house', 'path:/', ['en' => 'Home', 'es' => 'Inicio']);
        $manager->persist($home);

        // Level 1 → 2 → 3 → 4: Dashboard → Settings → Account → Profile, Security (and Notifications at 3)
        $dashboard = $this->item($menu, null, 2, 'Dashboard', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'dashboard'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:grid', null, ['en' => 'Dashboard', 'es' => 'Panel']);
        $manager->persist($dashboard);
        $overview = $this->item($menu, $dashboard, 0, 'Overview', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'dashboard', 'view' => 'overview'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:grid', null, ['en' => 'Overview', 'es' => 'Resumen']);
        $manager->persist($overview);
        $settings = $this->item($menu, $dashboard, 1, 'Settings', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'general'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:gear', 'authenticated', ['en' => 'Settings', 'es' => 'Ajustes']);
        $manager->persist($settings);
        $account = $this->item($menu, $settings, 0, 'Account', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'account'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:person', null, ['en' => 'Account', 'es' => 'Cuenta']);
        $manager->persist($account);
        $manager->persist($this->item($menu, $account, 0, 'Profile', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'account', 'tab' => 'profile'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:person-badge', null, ['en' => 'Profile', 'es' => 'Perfil']));
        $manager->persist($this->item($menu, $account, 1, 'Security', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'account', 'tab' => 'security'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:lock', null, ['en' => 'Security', 'es' => 'Seguridad']));
        $manager->persist($this->item($menu, $settings, 1, 'Notifications', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'notifications'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:bell', null, ['en' => 'Notifications', 'es' => 'Notificaciones']));

        $pagesSection = $this->item($menu, null, 3, 'Pages', MenuItem::ITEM_TYPE_SECTION, null, [], MenuItem::LINK_TYPE_ROUTE, null, null, null, ['en' => 'Pages', 'es' => 'Páginas']);
        $manager->persist($pagesSection);

        // Level 1 → 2 → 3 → 4: Configuration → General → Options, Profile; Configuration → Security → Two-factor, Sessions
        $configuration = $this->item($menu, null, 4, 'Configuration', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, [], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:sliders', null, ['en' => 'Configuration', 'es' => 'Configuración']);
        $manager->persist($configuration);
        $configGeneral = $this->item($menu, $configuration, 0, 'General', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'general'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:gear');
        $manager->persist($configGeneral);
        $manager->persist($this->item($menu, $configGeneral, 0, 'Options', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'general', 'tab' => 'options'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:sliders'));
        $manager->persist($this->item($menu, $configGeneral, 1, 'Profile', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'general', 'tab' => 'profile'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:person-badge'));
        $configSecurity = $this->item($menu, $configuration, 1, 'Security', MenuItem::ITEM_TYPE_LINK, HomeController::APP_SECURITY_ROUTE, ['section' => 'overview'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:shield-lock');
        $manager->persist($configSecurity);
        $manager->persist($this->item($menu, $configSecurity, 0, 'Two-factor', MenuItem::ITEM_TYPE_LINK, HomeController::APP_SECURITY_ROUTE, ['section' => '2fa'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:shield-check'));
        $manager->persist($this->item($menu, $configSecurity, 1, 'Sessions', MenuItem::ITEM_TYPE_LINK, HomeController::APP_SECURITY_ROUTE, ['section' => 'sessions'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:person-badge'));
        $manager->persist($this->item($menu, $configuration, 2, 'Admin only', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'general'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:shield-lock', 'admin', ['en' => 'Admin only', 'es' => 'Solo admin']));
        $manager->persist($this->item($menu, $configuration, 3, 'Auth OR admin', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'permissions', 'mode' => 'or'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:person-check', 'authenticated|admin', ['en' => 'Auth OR admin', 'es' => 'Autenticado O admin']));
        $manager->persist($this->item($menu, $configuration, 4, 'Admin area (path + auth)', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'permissions', 'mode' => 'and'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:shield', '(path:/admin|path:/operator)&authenticated', ['en' => 'Admin area (path + auth)', 'es' => 'Zona admin (ruta + auth)']));
        $manager->persist($this->item($menu, $configuration, 5, 'Always denied demo', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'permissions', 'mode' => 'deny'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:ban', '(authenticated|admin)&never', ['en' => 'Always denied demo', 'es' => 'Demo siempre denegado']));

        // Level 1 → 2 → 3 → 4: Documents → Doc A → A.1 → A.1.1, A.1.2; Doc B → B.1, B.2; Doc C → C.1 → C.1.a, C.1.b
        $documents = $this->item($menu, null, 5, 'Documents', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'documents'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:folder', null, ['en' => 'Documents', 'es' => 'Documentos']);
        $manager->persist($documents);
        $docA = $this->item($menu, $documents, 0, 'Doc A', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'documents', 'doc' => 'a'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark');
        $manager->persist($docA);
        $docA1 = $this->item($menu, $docA, 0, 'A.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'documents', 'doc' => 'a', 'section' => 'a1'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark-text');
        $manager->persist($docA1);
        $manager->persist($this->item($menu, $docA1, 0, 'A.1.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'documents', 'doc' => 'a', 'section' => 'a1', 'id' => 'a1-1'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark-text'));
        $manager->persist($this->item($menu, $docA1, 1, 'A.1.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'documents', 'doc' => 'a1-2'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark-text'));
        $manager->persist($this->item($menu, $docA, 1, 'A.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'documents', 'doc' => 'a2'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark-text'));
        $docB = $this->item($menu, $documents, 1, 'Doc B', MenuItem::ITEM_TYPE_LINK, HomeController::APP_ADMINISTRATION_ROUTE, [], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:folder2-open');
        $manager->persist($docB);
        $manager->persist($this->item($menu, $docB, 0, 'B.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'documents', 'doc' => 'b', 'section' => 'b1'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark-text'));
        $manager->persist($this->item($menu, $docB, 1, 'B.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'documents', 'doc' => 'b2'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark-text'));
        $manager->persist($this->item($menu, $docB, 2, 'B.3', MenuItem::ITEM_TYPE_LINK, HomeController::APP_SECURITY_ROUTE, ['section' => 'documents'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark-text'));
        $docC = $this->item($menu, $documents, 2, 'Doc C', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'documents', 'doc' => 'c'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:folder2-open');
        $manager->persist($docC);
        $docC1 = $this->item($menu, $docC, 0, 'C.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'documents', 'doc' => 'c1'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark');
        $manager->persist($docC1);
        $manager->persist($this->item($menu, $docC1, 0, 'C.1.a', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'documents', 'doc' => 'c', 'section' => 'c1a'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark-text'));
        $manager->persist($this->item($menu, $docC1, 1, 'C.1.b', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'documents', 'doc' => 'c1b'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark-text'));

        $resSection = $this->item($menu, null, 6, 'Resources', MenuItem::ITEM_TYPE_SECTION, null, [], MenuItem::LINK_TYPE_ROUTE, null, null, null, ['en' => 'Resources', 'es' => 'Recursos']);
        $manager->persist($resSection);
        $apiLink = $this->item($menu, null, 7, 'Menu API (JSON)', MenuItem::ITEM_TYPE_LINK, 'nowo_dashboard_menu_api', ['code' => 'sidebar'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:code-slash');
        $manager->persist($apiLink);

        $extSection = $this->item($menu, null, 8, 'External', MenuItem::ITEM_TYPE_SECTION, null, [], MenuItem::LINK_TYPE_ROUTE, null, null, null, ['en' => 'External', 'es' => 'Enlaces externos']);
        $manager->persist($extSection);
        $symfony = $this->item($menu, null, 9, 'Symfony', MenuItem::ITEM_TYPE_LINK, null, [], MenuItem::LINK_TYPE_EXTERNAL, 'https://symfony.com', 'bootstrap-icons:link');
        $manager->persist($symfony);
        $docs = $this->item($menu, null, 10, 'Documentation', MenuItem::ITEM_TYPE_LINK, null, [], MenuItem::LINK_TYPE_EXTERNAL, 'https://symfony.com/doc', 'bootstrap-icons:book');
        $manager->persist($docs);

        $footerSection = $this->item($menu, null, 11, 'Footer links', MenuItem::ITEM_TYPE_SECTION, null, [], MenuItem::LINK_TYPE_ROUTE, null, null, null, ['en' => 'Footer links', 'es' => 'Enlaces del pie']);
        $manager->persist($footerSection);
        $footerItems = [
            [['en' => 'About', 'es' => 'Acerca de'], 'about', 'bootstrap-icons:info-circle'],
            [['en' => 'Privacy', 'es' => 'Privacidad'], 'privacy', 'bootstrap-icons:shield-check'],
            [['en' => 'Terms', 'es' => 'Términos'], 'terms', 'bootstrap-icons:file-earmark-text'],
            [['en' => 'Contact', 'es' => 'Contacto'], 'contact', 'bootstrap-icons:envelope'],
            [['en' => 'Support', 'es' => 'Soporte'], 'support', 'bootstrap-icons:question-circle'],
        ];
        foreach ($footerItems as $i => $row) {
            [$translations, $section, $icon] = $row;
            $manager->persist($this->item($menu, null, 12 + $i, $translations['en'], MenuItem::ITEM_TYPE_LINK, HomeController::APP_INFO_ROUTE, ['section' => $section], MenuItem::LINK_TYPE_ROUTE, null, $icon, null, $translations));
        }
        // Status as parent with children (link + chevron; children visible/collapsible)
        $status = $this->item($menu, null, 17, 'Status', MenuItem::ITEM_TYPE_LINK, HomeController::APP_INFO_ROUTE, ['section' => 'status'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:activity', null, ['en' => 'Status', 'es' => 'Estado']);
        $manager->persist($status);
        $manager->persist($this->item($menu, $status, 0, 'Overview', MenuItem::ITEM_TYPE_LINK, HomeController::APP_INFO_ROUTE, ['section' => 'status', 'view' => 'overview'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:bar-chart', null, ['en' => 'Overview', 'es' => 'Resumen']));
        $manager->persist($this->item($menu, $status, 1, 'Incidents', MenuItem::ITEM_TYPE_LINK, HomeController::APP_INFO_ROUTE, ['section' => 'status', 'view' => 'incidents'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:exclamation-triangle', null, ['en' => 'Incidents', 'es' => 'Incidentes']));
        $manager->persist($this->item($menu, $status, 2, 'History', MenuItem::ITEM_TYPE_LINK, HomeController::APP_INFO_ROUTE, ['section' => 'status', 'view' => 'history'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:clock', null, ['en' => 'History', 'es' => 'Historial']));
    }

    /** Shorter menu for sidebar variant (premium/default) so we can see which one resolved. */
    private function addSidebarVariantItems(ObjectManager $manager, Menu $menu, string $variantLabel): void
    {
        $manager->persist($this->item($menu, null, 0, $variantLabel . ' menu', MenuItem::ITEM_TYPE_SECTION, null, [], MenuItem::LINK_TYPE_ROUTE, null, null, null, ['en' => $variantLabel . ' menu', 'es' => 'Menú ' . $variantLabel]));
        $manager->persist($this->item($menu, null, 1, 'Home', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, [], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:house', null, ['en' => 'Home', 'es' => 'Inicio']));
        $manager->persist($this->item($menu, null, 2, $variantLabel . ' feature', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => strtolower($variantLabel)], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:star', null, ['en' => $variantLabel . ' feature', 'es' => 'Función ' . $variantLabel]));
        $manager->persist($this->item($menu, null, 3, 'Configuration', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, [], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:gear', null, ['en' => 'Configuration', 'es' => 'Configuración']));
    }

    private function addAsideItems(ObjectManager $manager, Menu $menu): void
    {
        $filtersSection = $this->item($menu, null, 0, 'Filters', MenuItem::ITEM_TYPE_SECTION);
        $manager->persist($filtersSection);
        $all = $this->item($menu, null, 1, 'All', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['filter' => 'all'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:list-ul', 'authenticated');
        $all->setPermissionKeys(['authenticated', 'admin']);
        $all->setIsUnanimous(false); // OR: authenticated OR admin
        $manager->persist($all);
        $manager->persist($this->item($menu, null, 2, 'Recent', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['filter' => 'recent'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:clock', 'path:/__never__'));

        // Level 1 → 2 → 3 → 4: Category A → A1 → A1.1 → A1.1.1, A1.1.2; A2; Category B → B1 → B1.1, B1.2; B2
        $catSection = $this->item($menu, null, 3, 'Categories', MenuItem::ITEM_TYPE_SECTION);
        $manager->persist($catSection);
        $catA = $this->item($menu, null, 4, 'Category A', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['category' => 'a'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:folder', 'path:/__never__');
        $manager->persist($catA);
        $catA1 = $this->item($menu, $catA, 0, 'A1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'category', 'id' => 'a1'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:tag');
        $manager->persist($catA1);
        $catA11 = $this->item($menu, $catA1, 0, 'A1.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'category', 'id' => 'a1-1'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark');
        $manager->persist($catA11);
        $manager->persist($this->item($menu, $catA11, 0, 'A1.1.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'category', 'id' => 'a1-1-1'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark'));
        $manager->persist($this->item($menu, $catA11, 1, 'A1.1.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_SECURITY_ROUTE, ['section' => 'category', 'id' => 'a1-1-2'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark'));
        $manager->persist($this->item($menu, $catA1, 1, 'A1.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_SECURITY_ROUTE, ['section' => 'category', 'id' => 'a1-2'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark'));
        $manager->persist($this->item($menu, $catA, 1, 'A2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_SECURITY_ROUTE, ['section' => 'category', 'id' => 'a2'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:tag'));
        $catB = $this->item($menu, null, 5, 'Category B', MenuItem::ITEM_TYPE_LINK, HomeController::APP_ADMINISTRATION_ROUTE, [], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:folder');
        $manager->persist($catB);
        $catB1 = $this->item($menu, $catB, 0, 'B1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['category' => 'b', 'sub' => 'b1'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:tag');
        $manager->persist($catB1);
        $manager->persist($this->item($menu, $catB1, 0, 'B1.1', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'category', 'id' => 'b1-1'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark'));
        $manager->persist($this->item($menu, $catB1, 1, 'B1.2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_SECURITY_ROUTE, ['section' => 'category', 'id' => 'b1-2'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:file-earmark'));
        $manager->persist($this->item($menu, $catB, 1, 'B2', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, ['section' => 'category', 'id' => 'b2'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:tag'));

        $quickSection = $this->item($menu, null, 6, 'Quick links', MenuItem::ITEM_TYPE_SECTION);
        $manager->persist($quickSection);
        $manager->persist($this->item($menu, null, 7, 'API Sidebar', MenuItem::ITEM_TYPE_LINK, 'nowo_dashboard_menu_api', ['code' => 'sidebar'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:code-slash'));
        $apiFooter = $this->item($menu, null, 8, 'API Footer', MenuItem::ITEM_TYPE_LINK, 'nowo_dashboard_menu_api', ['code' => 'footer'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:link');
        $apiFooter->setPermissionKeys(['authenticated', 'path:/']);
        $apiFooter->setIsUnanimous(true); // AND: authenticated AND path:/
        $manager->persist($apiFooter);
    }

    private function addFooterItems(ObjectManager $manager, Menu $menu): void
    {
        $footerItems = [
            [['en' => 'About', 'es' => 'Acerca de'], 'about', 'bootstrap-icons:info-circle'],
            [['en' => 'Privacy', 'es' => 'Privacidad'], 'privacy', 'bootstrap-icons:shield-check'],
            [['en' => 'Terms', 'es' => 'Términos'], 'terms', 'bootstrap-icons:file-earmark-text'],
            [['en' => 'Contact', 'es' => 'Contacto'], 'contact', 'bootstrap-icons:envelope'],
            [['en' => 'Support', 'es' => 'Soporte'], 'support', 'bootstrap-icons:question-circle'],
            [['en' => 'Status', 'es' => 'Estado'], 'status', 'bootstrap-icons:activity'],
        ];
        foreach ($footerItems as $i => $row) {
            [$translations, $section, $icon] = $row;
            $manager->persist($this->item($menu, null, $i, $translations['en'], MenuItem::ITEM_TYPE_LINK, HomeController::APP_INFO_ROUTE, ['section' => $section], MenuItem::LINK_TYPE_ROUTE, null, $icon, null, $translations));
        }
    }

    private function addDropdownItems(ObjectManager $manager, Menu $menu): void
    {
        $manager->persist($this->item($menu, null, 0, 'Settings', MenuItem::ITEM_TYPE_LINK, HomeController::APP_CONFIGURATION_ROUTE, [], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:gear'));
        $manager->persist($this->item($menu, null, 1, 'Help', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'help'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:question-circle'));
        $manager->persist($this->item($menu, null, 2, 'Logout', MenuItem::ITEM_TYPE_LINK, HomeController::APP_HOME_ROUTE, ['page' => 'logout'], MenuItem::LINK_TYPE_ROUTE, null, 'bootstrap-icons:box-arrow-right'));
    }

    private function addLocaleSwitcherItems(ObjectManager $manager, Menu $menu): void
    {
        $manager->persist($this->item($menu, null, 0, 'English', MenuItem::ITEM_TYPE_LINK, SystemController::APP_SWITCH_LOCALE_ROUTE, ['_locale' => 'en'], MenuItem::LINK_TYPE_ROUTE, null, null, null, ['en' => 'English', 'es' => 'Inglés', 'fr' => 'Anglais']));
        $manager->persist($this->item($menu, null, 1, 'Español', MenuItem::ITEM_TYPE_LINK, SystemController::APP_SWITCH_LOCALE_ROUTE, ['_locale' => 'es'], MenuItem::LINK_TYPE_ROUTE, null, null, null, ['en' => 'Spanish', 'es' => 'Español', 'fr' => 'Espagnol']));
        $manager->persist($this->item($menu, null, 2, 'Français', MenuItem::ITEM_TYPE_LINK, SystemController::APP_SWITCH_LOCALE_ROUTE, ['_locale' => 'fr'], MenuItem::LINK_TYPE_ROUTE, null, null, null, ['en' => 'French', 'es' => 'Francés', 'fr' => 'Français']));
    }

    private function item(
        Menu $menu,
        ?MenuItem $parent,
        int $position,
        string $label,
        string $itemType,
        ?string $routeName = null,
        array $routeParams = [],
        string $linkType = MenuItem::LINK_TYPE_ROUTE,
        ?string $externalUrl = null,
        ?string $icon = null,
        ?string $permissionKey = null,
        array $translations = []
    ): MenuItem {
        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setParent($parent);
        $item->setPosition($position);
        $item->setLabel($label);
        if ($translations !== []) {
            $item->setTranslations($translations);
        }
        $item->setItemType($itemType);
        $item->setLinkType($linkType);
        if ($linkType === MenuItem::LINK_TYPE_ROUTE && $routeName !== null) {
            $item->setRouteName($routeName);
            $item->setRouteParams($routeParams ?: null);
        }
        if ($externalUrl !== null) {
            $item->setExternalUrl($externalUrl);
        }
        if ($icon !== null) {
            $item->setIcon($icon);
        }
        if ($permissionKey !== null) {
            $item->setPermissionKey($permissionKey);
        }

        return $item;
    }
}
