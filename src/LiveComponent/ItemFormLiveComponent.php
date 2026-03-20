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

    /**
     * Explicit item id for re-hydrating entity private fields (e.g. translations) reliably.
     * LiveComponents may deserialize entities without private state.
     */
    #[LiveProp]
    public ?int $itemId = null;

    protected function instantiateForm(): FormInterface
    {
        $initialData = $this->initialFormData ?? new MenuItem();
        // LiveComponent may serialize/deserialize entity-like props and lose private fields
        // (e.g. translations JSON), and sometimes even `id`.
        // When editing an existing item, always re-fetch it from DB to avoid wiping translations on save.
        $itemId = $this->itemId;
        if ($itemId === null) {
            $itemId = $initialData instanceof MenuItem ? $initialData->getId() : null;
        }
        if ($itemId === null) {
            // Parse itemId from actionUrl (route pattern: /{menuId}/item/{itemId}/edit)
            if ($this->actionUrl !== '') {
                $matches = [];
                if (preg_match('#/item/(\d+)/edit#', $this->actionUrl, $matches) === 1) {
                    $itemId = (int) ($matches[1] ?? 0);
                }
            }
        }
        if ($itemId !== null) {
            // Avoid returning a potentially stale managed instance from Doctrine's identity map.
            // Using clear() + repository findOneBy() makes sure we fetch the current row.
            $this->entityManager->clear(MenuItem::class);
            $repo  = $this->entityManager->getRepository(MenuItem::class);
            $fresh = $repo->findOneBy(['id' => $itemId]);
            if ($fresh instanceof MenuItem) {
                $initialData = $fresh;
            }
        }

        return $this->formFactory->create(MenuItemType::class, $initialData, [
            'app_routes'        => $this->appRoutes,
            'menu'              => $this->menu,
            'exclude_ids'       => $this->excludeIds,
            'locale'            => $this->locale,
            'available_locales' => $this->locales,
            // Keep CSRF consistent across Symfony versions (and match stateless_token_ids in the demos).
            'csrf_token_id' => 'submit',
            'section'       => $this->sectionFocus,
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
     * Used only for debug in the LiveComponent template.
     *
     * @return array<string, string> Locale => value
     */
    public function getTranslationsDebug(): array
    {
        $data = $this->getForm()->getData();
        if ($data instanceof MenuItem) {
            return $data->getTranslations() ?? [];
        }

        return [];
    }

    /**
     * Used only for debug in the LiveComponent template.
     */
    public function getTranslationsKeysDebug(): string
    {
        $translations = $this->getTranslationsDebug();

        return implode(',', array_keys($translations));
    }

    /**
     * Debug: values currently present in the locale TextType fields.
     *
     * @return array<string, string|null>
     */
    public function getLocaleFieldValuesDebug(): array
    {
        $out  = [];
        $form = $this->getForm();
        if (!$form->has('basic')) {
            return $out;
        }

        $basic = $form->get('basic');
        foreach ($this->locales as $locale) {
            $fieldName = 'label_' . $locale;
            if (!$basic->has($fieldName)) {
                continue;
            }

            $field        = $basic->get($fieldName);
            $out[$locale] = $field->getData();
        }

        return $out;
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
