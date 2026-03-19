<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\LiveComponent;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\MenuItemType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

use function is_array;

/**
 * Live form for creating/editing a menu item. Toggles parent/link fields by itemType (section/divider = root only, hide link; link = show parent and link unless has children).
 *
 * @internal
 */
#[AsLiveComponent(
    name: 'dashboard_menu_item_form',
    template: '@NowoDashboardMenuBundle/components/ItemFormLiveComponent.html.twig',
)]
final class ItemFormLiveComponent
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly \Doctrine\ORM\EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[LiveProp]
    public Menu $menu;

    /** @var array<string, array{label: string, params: list<string>}> */
    #[LiveProp]
    public array $appRoutes = [];

    /** @var list<int> */
    #[LiveProp]
    public array $excludeIds = [];

    #[LiveProp]
    public string $locale = 'en';

    #[LiveProp]
    public bool $isEdit = false;

    #[LiveProp]
    public bool $itemHasChildren = false;

    #[LiveProp]
    public string $actionUrl = '';

    /** @var list<string> */
    #[LiveProp]
    public array $locales = [];

    #[LiveProp]
    public ?MenuItem $initialFormData = null;

    /** URL to redirect to after successful save (e.g. menu show page). */
    #[LiveProp]
    public string $redirectToUrl = '';

    /** When set ('basic' or 'config'), only that section is shown and submitted. */
    #[LiveProp]
    public ?string $sectionFocus = null;

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(MenuItemType::class, $this->initialFormData ?? new MenuItem(), [
            'app_routes'        => $this->appRoutes,
            'menu'              => $this->menu,
            'exclude_ids'       => $this->excludeIds,
            'locale'            => $this->locale,
            'available_locales' => $this->locales,
            // Keep CSRF consistent across Symfony versions (and match stateless_token_ids in the demos).
            'csrf_token_id'    => 'submit',
            'section'           => $this->sectionFocus,
        ]);
    }

    /**
     * Form values as flat array (ComponentWithFormTrait may nest under form name).
     *
     * @return array<string, mixed>
     */
    private function getFormValuesFlat(): array
    {
        $name   = $this->getForm()->getName();
        $values = $this->formValues;
        $root   = isset($values[$name]) && is_array($values[$name]) ? $values[$name] : (is_array($values) ? $values : []);
        $basic  = is_array($root['basic'] ?? null) ? $root['basic'] : [];
        $config = is_array($root['config'] ?? null) ? $root['config'] : [];

        return array_merge($basic, $config);
    }

    public function getItemType(): string
    {
        $values = $this->getFormValuesFlat();
        $type   = $values['itemType'] ?? null;
        if ($type !== null && $type !== '') {
            return (string) $type;
        }
        /** @var MenuItem|null $data */
        $data = $this->getForm()->getData();

        return $data instanceof MenuItem ? $data->getItemType() : MenuItem::ITEM_TYPE_LINK;
    }

    public function getLinkType(): string
    {
        $values = $this->getFormValuesFlat();
        $v      = $values['linkType'] ?? null;
        if ($v !== null && $v !== '') {
            return (string) $v;
        }
        /** @var MenuItem|null $data */
        $data = $this->getForm()->getData();
        if ($data instanceof MenuItem && $data->getLinkType() !== null) {
            return $data->getLinkType();
        }

        return MenuItem::LINK_TYPE_ROUTE;
    }

    public function showParentField(): bool
    {
        $type = $this->getItemType();

        return $type !== MenuItem::ITEM_TYPE_SECTION && $type !== MenuItem::ITEM_TYPE_DIVIDER;
    }

    public function showLinkFields(): bool
    {
        $type = $this->getItemType();
        if ($type !== MenuItem::ITEM_TYPE_LINK) {
            return false;
        }

        return !$this->itemHasChildren;
    }

    public function showRouteFields(): bool
    {
        return $this->showLinkFields() && $this->getLinkType() !== MenuItem::LINK_TYPE_EXTERNAL;
    }

    public function showExternalUrlField(): bool
    {
        return $this->showLinkFields() && $this->getLinkType() === MenuItem::LINK_TYPE_EXTERNAL;
    }

    /**
     * Suggested route params (for hint) from appRoutes for the currently selected route.
     *
     * @return array<string, string>
     */
    public function getSuggestedRouteParams(): array
    {
        $values = $this->getFormValuesFlat();
        $name   = $values['routeName'] ?? null;
        if ($name === null || $name === '' || !isset($this->appRoutes[$name])) {
            return [];
        }
        $params = $this->appRoutes[$name]['params'] ?? [];
        $out    = [];
        foreach ($params as $p) {
            $out[$p] = '';
        }

        return $out;
    }

    #[LiveAction]
    public function save(): RedirectResponse
    {
        $this->submitForm();
        /** @var MenuItem $item */
        $item = $this->getForm()->getData();
        if ($item->getItemType() === MenuItem::ITEM_TYPE_SECTION || $item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
            $item->setParent(null);
        }
        if ($item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
            $item->setLabel('');
            $item->setIcon(null);
            $item->setTranslations(null);
        }
        if ($item->getItemType() === MenuItem::ITEM_TYPE_LINK && $item->getChildren()->count() > 0) {
            $item->setLinkType(null);
            $item->setRouteName(null);
            $item->setRouteParams(null);
            $item->setExternalUrl(null);
        }
        if ($item->getMenu() === null) {
            $item->setMenu($this->menu);
        }
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $this->requestStack->getSession();
        $session->getFlashBag()->add('success', $this->isEdit ? 'Item updated.' : 'Item created.');

        return new RedirectResponse($this->redirectToUrl);
    }
}
