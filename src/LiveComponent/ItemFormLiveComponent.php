<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\LiveComponent;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Concurrency\DashboardMenuConflictAnswer;
use Nowo\DashboardMenuBundle\Concurrency\DashboardMenuTransaction;
use Nowo\DashboardMenuBundle\Concurrency\EditVersion;
use Nowo\DashboardMenuBundle\Concurrency\StaleEditException;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\MenuItemType;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

use function bin2hex;
use function is_array;
use function random_bytes;

/**
 * Live form for creating/editing a menu item. Toggles parent/link fields by itemType (divider = root only; sections can have a parent; link = show parent and link unless has children).
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

    /**
     * @param DashboardMenuTransaction|null $transaction the write unit of the save (2.23); null = one on $entityManager
     * @param DashboardMenuConflictAnswer|null $conflictAnswer the 409 answer of a lock conflict (2.23); null = 409, Retry-After 1
     */
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly ?DashboardMenuTransaction $transaction = null,
        private readonly ?DashboardMenuConflictAnswer $conflictAnswer = null,
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

    /**
     * True when the component renders inside a host page/form scaffold <form> (2.7): no own form tag or submit button;
     * the scaffold form posts natively to the edit action. The bundle's full item page uses the UiKit 2.2 body-only
     * form page instead (own form, {@see $cancelHref}) since 2.8; kept for host templates.
     */
    #[LiveProp]
    public bool $embedded = false;

    /**
     * Cancel link of the own form (full item page, 2.8); null = the cancel button closes the enclosing UiKit dialog.
     */
    #[LiveProp]
    public ?string $cancelHref = null;

    /** When set ('basic' or 'config'), only that section is shown and submitted. */
    #[LiveProp]
    public ?string $sectionFocus = null;

    /**
     * Explicit item id for re-hydrating entity private fields (e.g. translations) reliably.
     * LiveComponents may deserialize entities without private state.
     */
    #[LiveProp]
    public ?int $itemId = null;

    /**
     * Prevent refetching the MenuItem from DB on every LiveComponent update.
     *
     * LiveComponent re-renders when bound fields change; if we always refetch,
     * we'd overwrite in-progress (unsaved) user edits (e.g. per-locale labels).
     */
    #[LiveProp(writable: false)]
    public ?int $hydratedItemId = null;

    /**
     * `edit_version` of the item when the form was first built (2.24, REQ-RUNTIME-003 rule 4): a LiveProp, so it is the
     * version the admin saw and not the one a later re-render reads. The save refuses an older one.
     */
    #[LiveProp(writable: false)]
    public ?int $baseVersion = null;

    /** One token per new-item form (2.24): the same form saved twice creates one item. */
    #[LiveProp(writable: false)]
    public ?string $submitToken = null;

    /**
     * @return FormInterface<MenuItem>
     */
    protected function instantiateForm(): FormInterface
    {
        $initialData = $this->initialFormData ?? new MenuItem();
        // LiveComponent may serialize/deserialize entity-like props and lose private fields
        // (e.g. translations JSON), and sometimes even `id`.
        // When editing an existing item, always re-fetch it from DB to avoid wiping translations on save.
        $itemId = $this->itemId;
        $itemId ??= $initialData->getId();
        // Parse itemId from actionUrl (route pattern: /{menuId}/item/{itemId}/edit)
        if ($itemId === null && $this->actionUrl !== '') {
            $matches = [];
            if (preg_match('#/item/(\d+)/edit#', $this->actionUrl, $matches) === 1) {
                $itemId = (int) $matches[1];
            }
        }
        if ($itemId !== null) {
            // Gear-only (config) modal has no `icon.itemType` field; Live deserializes `initialFormData`
            // without reliable private state, so skipping hydration would rebuild the form as link.
            $configGearModal = $this->sectionFocus === 'config';
            $needsHydration  = $configGearModal
                || $this->hydratedItemId === null
                || $this->hydratedItemId !== $itemId;
            if ($needsHydration) {
                // Avoid returning a potentially stale managed instance from Doctrine's identity map.
                // Detach only this entity (not all tracked entities) before re-fetching to avoid
                // overwriting in-progress edits of other managed objects in the same request.
                $this->entityManager->detach($initialData);
                $fresh = $this->entityManager->find(MenuItem::class, $itemId);
                if ($fresh instanceof MenuItem) {
                    $initialData = $fresh;
                }

                // @igor-ignore - UX Live Component holds request-scoped form state
                $this->hydratedItemId = $itemId;
                // @igor-ignore - UX Live Component holds request-scoped form state
                $this->baseVersion ??= $initialData->getEditVersion();
            }
        } else {
            // @igor-ignore - UX Live Component holds request-scoped form state
            $this->submitToken ??= bin2hex(random_bytes(16));
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
            'live_model'    => true,
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
        $root   = isset($values[$name]) && is_array($values[$name]) ? $values[$name] : $values;
        $basic  = is_array($root['basic'] ?? null) ? $root['basic'] : [];
        $icon   = is_array($root['icon'] ?? null) ? $root['icon'] : [];
        $config = is_array($root['config'] ?? null) ? $root['config'] : [];

        return array_merge($basic, $icon, $config);
    }

    public function getItemType(): string
    {
        $form     = $this->getForm();
        $iconForm = $form->has('icon') ? $form->get('icon') : null;
        // Gear (config-only) and basic-only partials omit `icon.itemType`. Live `formValues` may still carry a stale
        // `itemType` from a previous render; prefer the persisted entity type when the field is not in the tree.
        $iconFormHasItemType = $iconForm instanceof FormInterface && $iconForm->has('itemType');

        if ($iconFormHasItemType) {
            $values = $this->getFormValuesFlat();
            $type   = $values['itemType'] ?? null;
            if ($type !== null && $type !== '') {
                return (string) $type;
            }
        }

        /** @var MenuItem|null $data */
        $data = $form->getData();

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

        return $type !== MenuItem::ITEM_TYPE_DIVIDER;
    }

    public function showSectionCollapsibleField(): bool
    {
        return $this->getItemType() === MenuItem::ITEM_TYPE_SECTION;
    }

    public function showClassicLinkFields(): bool
    {
        $type = $this->getItemType();

        return $type === MenuItem::ITEM_TYPE_LINK && !$this->itemHasChildren;
    }

    public function showServiceLinkFields(): bool
    {
        return $this->getItemType() === MenuItem::ITEM_TYPE_SERVICE;
    }

    /** True when classic route/external link fields apply (itemType link, no children). */
    public function showLinkFields(): bool
    {
        return $this->showClassicLinkFields();
    }

    public function showRouteFields(): bool
    {
        return $this->showClassicLinkFields() && $this->getLinkType() !== MenuItem::LINK_TYPE_EXTERNAL;
    }

    public function showExternalUrlField(): bool
    {
        return $this->showClassicLinkFields() && $this->getLinkType() === MenuItem::LINK_TYPE_EXTERNAL;
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
        $params = $this->appRoutes[$name]['params'];
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
        if ($item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
            $item->normalizeDividerState();
        }
        // @igor-ignore - UX Live Component holds request-scoped form state
        if ($item->getItemType() === MenuItem::ITEM_TYPE_LINK && $item->getChildren()->count() > 0) {
            // @igor-ignore - UX Live Component holds request-scoped form state
            $item->setLinkType(null);
            // @igor-ignore - UX Live Component holds request-scoped form state
            $item->setRouteName(null);
            // @igor-ignore - UX Live Component holds request-scoped form state
            $item->setRouteParams(null);
            // @igor-ignore - UX Live Component holds request-scoped form state
            $item->setExternalUrl(null);
        }
        // @igor-ignore - UX Live Component holds request-scoped form state
        if ($item->getMenu() === null) {
            // @igor-ignore - UX Live Component holds request-scoped form state
            $item->setMenu($this->menu);
        }
        // One write unit (2.23, REQ-RUNTIME-003): a lock conflict answers 409 CONFLICT_001 / CONFLICT_002.
        $transaction = $this->transaction ?? new DashboardMenuTransaction($this->entityManager);
        $baseVersion = $this->baseVersion;
        $token       = $item->getId() === null ? EditVersion::submitToken($this->submitToken) : '';
        if ($item->getId() !== null) {
            // With the form's changes, before the unit computes the change sets (2.24); a stale save drops the entity.
            $item->bumpEditVersion();
        }
        // @igor-ignore - request-scoped closure, run at once and dropped (never stored in the shared service)
        $save = static function () use ($transaction, $item, $baseVersion, $token): string {
            try {
                // @igor-ignore - request-scoped closure, run at once and dropped (never stored in the shared service)
                return $transaction->run(static function (EntityManagerInterface $em) use ($item, $baseVersion, $token): string {
                    if ($item->getId() !== null) {
                        // The version the admin saw, read under a lock of the item row (2.24, REQ-RUNTIME-003 rule 4).
                        $repository = $em->getRepository(MenuItem::class);
                        EditVersion::assertCurrent($repository instanceof MenuItemRepository ? $repository->storedEditVersion($item->getId()) : null, $baseVersion);
                    } elseif ($token !== '') {
                        $repository = $em->getRepository(MenuItem::class);
                        if ($repository instanceof MenuItemRepository && $repository->submitTokenExists($token)) {
                            return 'duplicate';
                        }
                        // @igor-ignore - UX Live Component holds request-scoped form state
                        $item->setSubmitToken($token);
                    }
                    $em->persist($item);

                    return 'saved';
                });
                // @phpstan-ignore catch.neverThrown (thrown by the closure the write unit runs)
            } catch (StaleEditException) {
                return 'stale';
            } catch (UniqueConstraintViolationException) {
                // The token's unique key closed a race the check could not see.
                return 'duplicate';
            }
        };
        $request = $this->requestStack->getCurrentRequest();
        $outcome = $request instanceof Request ? ($this->conflictAnswer ?? new DashboardMenuConflictAnswer())->guard($request, $save) : $save();

        // @igor-ignore - UX Live Component holds request-scoped form state
        /** @var Session $session */
        // @igor-ignore - UX Live Component holds request-scoped form state
        $session = $this->requestStack->getSession();
        // @igor-ignore - UX Live Component holds request-scoped form state
        $session->getFlashBag()->add(
            $outcome === 'stale' ? 'error' : 'success',
            match ($outcome) {
                'stale'     => 'This item was changed by someone else while you were editing it. Reload it and apply your change again.',
                'duplicate' => 'Item already created: the form was submitted twice, so only one item was added.',
                default     => $this->isEdit ? 'Item updated.' : 'Item created.',
            },
        );

        return new RedirectResponse($this->redirectToUrl);
    }
}
