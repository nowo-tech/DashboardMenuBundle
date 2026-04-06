<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Closure;
use Doctrine\ORM\QueryBuilder;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\DataTransformer\JsonToArrayTransformer;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_merge;
use function array_unique;
use function array_values;
use function in_array;
use function ksort;
use function spl_object_id;

/**
 * Form type for menu item configuration: parent, link (route / external URL / service resolver), target, permission.
 * Shown in the dashboard with a gear icon (configuration).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuItemConfigType extends AbstractType
{
    /**
     * @param list<string>              $permissionKeyChoices
     * @param array<string, string>     $menuLinkResolverChoices service id => label (after compiler pass)
     */
    public function __construct(
        private readonly MenuItemRepository $menuItemRepository,
        private readonly array $permissionKeyChoices = [],
        private readonly array $menuLinkResolverChoices = [],
        private readonly string $defaultLocale = 'en',
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, array{label: string, params: list<string>}> $appRoutes */
        $appRoutes    = $options['app_routes'];
        $routeChoices = $this->buildRouteChoices($appRoutes);
        $t            = fn (string $id): string => $this->translator instanceof TranslatorInterface ? $this->translator->trans($id, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $id;

        // With inherit_data (nested under MenuItemType), getData() is often null during buildForm;
        // MenuItemType passes the same model via the menu_item option.
        $formData = $this->resolveMenuItemFormData($builder, $options);
        $itemType = MenuItem::ITEM_TYPE_LINK;
        if ($formData instanceof MenuItem) {
            $itemType = $formData->getItemType() ?? MenuItem::ITEM_TYPE_LINK;
        }
        $hasChildren = $formData instanceof MenuItem && !$formData->getChildren()->isEmpty();

        // Full item form (modal / new / edit all sections): keep every link field so JS can toggle by itemType.
        // Config-only partial (gear): expose only fields that apply to the current item type.
        $configOnly           = $options['item_form_section'] === 'config';
        $showClassicLinkBlock = !$configOnly || ($itemType === MenuItem::ITEM_TYPE_LINK && !$hasChildren);
        // Service: resolver is used for the parent href; optional dynamic children are merged in the tree even when DB children exist.
        $showServiceLinkBlock = !$configOnly || $itemType === MenuItem::ITEM_TYPE_SERVICE;
        // routeParams: link only (not service — service has no static route params).
        $showRouteParams = $showClassicLinkBlock;
        // targetBlank: link AND service (service items can open in a new tab via their resolved href).
        $showTargetBlank = $showClassicLinkBlock || $itemType === MenuItem::ITEM_TYPE_SERVICE;

        $resolverChoices = [];
        foreach ($this->menuLinkResolverChoices as $id => $label) {
            $resolverChoices[$id] = $id;
        }
        if ($formData instanceof MenuItem) {
            $currentResolver = $formData->getLinkResolver();
            if ($currentResolver !== null && $currentResolver !== '' && !in_array($currentResolver, $resolverChoices, true)) {
                $resolverChoices[$currentResolver . ' (current)'] = $currentResolver;
            }
        }
        ksort($resolverChoices, SORT_NATURAL);
        if ($showServiceLinkBlock) {
            if ($resolverChoices !== []) {
                $builder->add('linkResolver', ChoiceType::class, [
                    'required'                  => true,
                    'label'                     => 'form.menu_item_type.link_resolver.label',
                    'placeholder'               => $t('form.menu_item_type.link_resolver.placeholder'),
                    'choices'                   => $resolverChoices,
                    'choice_translation_domain' => false,
                    'attr'                      => ['class' => 'form-select'],
                    'row_attr'                  => ['class' => 'mb-1'],
                    'label_attr'                => ['class' => 'form-label'],
                    'help'                      => 'form.menu_item_type.link_resolver.help',
                    'autocomplete'              => true,
                    'tom_select_options'        => NowoDashboardMenuBundle::TOM_SELECT_MODAL_DROPDOWN,
                ]);
            } else {
                $builder->add('linkResolver', TextType::class, [
                    'required'   => false,
                    'label'      => 'form.menu_item_type.link_resolver.label',
                    'attr'       => [
                        'class'       => 'form-control font-monospace',
                        'placeholder' => $t('form.menu_item_type.link_resolver.service_id_placeholder'),
                    ],
                    'row_attr'   => ['class' => 'mb-1'],
                    'label_attr' => ['class' => 'form-label'],
                    'help'       => 'form.menu_item_type.link_resolver.help_free_text',
                ]);
            }
        }

        if ($showClassicLinkBlock) {
            $builder
                ->add('linkType', ChoiceType::class, [
                    'choices' => [
                        'form.menu_item_type.link_type.route'        => MenuItem::LINK_TYPE_ROUTE,
                        'form.menu_item_type.link_type.external_url' => MenuItem::LINK_TYPE_EXTERNAL,
                    ],
                    'label' => 'form.menu_item_type.link_type.label',
                    'attr'               => ['class' => 'form-select'],
                    'row_attr'           => ['class' => 'mb-1'],
                    'label_attr'         => ['class' => 'form-label'],
                    'autocomplete'       => true,
                    'tom_select_options' => NowoDashboardMenuBundle::TOM_SELECT_MODAL_DROPDOWN,
                ])
                ->add('routeName', ChoiceType::class, [
                    'required'    => false,
                    'label'       => 'form.menu_item_type.route_name.label',
                    'placeholder' => $t('form.menu_item_type.route_name.placeholder'),
                    'choices'     => $routeChoices,
                    'attr'        => ['class' => 'form-select'],
                    'row_attr'    => ['class' => 'mb-1'],
                    'label_attr'  => ['class' => 'form-label'],
                    'choice_attr' => static function ($choice, $key, $value) use ($appRoutes): array {
                        $params = $appRoutes[$value]['params'] ?? [];

                        return ['data-params' => json_encode($params)];
                    },
                    'autocomplete'       => true,
                    'tom_select_options' => NowoDashboardMenuBundle::TOM_SELECT_MODAL_DROPDOWN,
                ])
                ->add('externalUrl', UrlType::class, [
                    'required'   => false,
                    'label'      => 'form.menu_item_type.external_url.label',
                    'attr'       => ['class' => 'form-control', 'placeholder' => $t('form.menu_item_type.external_url.placeholder')],
                    'row_attr'   => ['class' => 'mb-1'],
                    'label_attr' => ['class' => 'form-label'],
                ]);
        }

        if ($showRouteParams) {
            $builder->add('routeParams', TextType::class, [
                'required'   => false,
                'label'      => 'form.menu_item_type.route_params.label',
                'attr'       => ['class' => 'form-control font-monospace', 'placeholder' => $t('form.menu_item_type.route_params.placeholder')],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
            ]);
            $builder->get('routeParams')->addModelTransformer(new JsonToArrayTransformer());
        }

        if ($showTargetBlank) {
            $builder->add('targetBlank', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_item_type.target_blank.label',
                'attr'       => ['class' => 'form-check-input'],
                'row_attr'   => ['class' => 'ms-3 mb-1 form-check'],
                'label_attr' => ['class' => 'form-check-label'],
            ]);
        }

        $choices              = $this->buildPermissionKeyChoices($t, $formData);
        $permissionKeyOptions = [
            'required'                  => false,
            'label'                     => 'form.menu_item_type.permission_keys.label',
            'row_attr'                  => ['class' => 'mb-1'],
            'label_attr'                => ['class' => 'form-label'],
            'choices'                   => $choices,
            'placeholder'               => $t('form.menu_item_type.permission_keys.placeholder'),
            'choice_translation_domain' => false,
            'attr'                      => ['class' => 'form-select'],
            'autocomplete'              => true,
            'multiple'                  => true,
            'tom_select_options'        => array_merge([
                'plugins'          => ['remove_button'],
                'closeAfterSelect' => false,
                'hidePlaceholder'  => true,
            ], NowoDashboardMenuBundle::TOM_SELECT_MODAL_DROPDOWN),
        ];
        $builder->add('permissionKeys', ChoiceType::class, $permissionKeyOptions);
        $builder->add('isUnanimous', CheckboxType::class, [
            'required'   => false,
            'label'      => 'form.menu_item_type.is_unanimous.label',
            'help'       => 'form.menu_item_type.is_unanimous.help',
            'attr'       => ['class' => 'form-check-input'],
            'row_attr'   => ['class' => 'ms-3 mb-1 form-check'],
            'label_attr' => ['class' => 'form-check-label'],
        ]);

        $menu = $options['menu'];
        if ($menu instanceof Menu) {
            $locale         = $options['locale'];
            $baseExcludeIds = array_values(array_unique($options['exclude_ids']));
            /** @var MenuItem|null $menuItemFormData set by reference: EntityType may reload choices after bind */
            $menuItemFormData = $formData instanceof MenuItem ? $formData : null;
            $itemRepository   = $this->menuItemRepository;
            $queryBuilder     = static function ($_repository) use ($itemRepository, $menu, $baseExcludeIds, $menuItemFormData): QueryBuilder {
                unset($_repository);
                $excludeIds = $baseExcludeIds;
                if ($menuItemFormData instanceof MenuItem && $menuItemFormData->getId() !== null) {
                    $excludeIds = array_values(array_unique(array_merge(
                        $excludeIds,
                        $itemRepository->findIdsInSubtreeStartingAt($menu, (int) $menuItemFormData->getId()),
                    )));
                }

                return $itemRepository->getPossibleParentsQueryBuilder($menu, $excludeIds);
            };
            $builder->add('parent', EntityType::class, [
                'class'         => MenuItem::class,
                'query_builder' => $queryBuilder,
                'help'          => $t('form.menu_item_type.parent.help_inheritance'),
                'choice_label'  => static function (MenuItem $item) use ($locale): string {
                    return self::parentChoiceBreadcrumbLabel($item, $locale);
                },
                'placeholder' => $t('form.menu_item_type.parent.placeholder'),
                'required'    => false,
                'label'       => 'form.menu_item_type.parent.label',
                'attr'        => ['class' => 'form-select'],
                'row_attr'    => ['class' => 'mb-1'],
                'label_attr'  => ['class' => 'form-label'],
                // No UX Autocomplete here: remote Tom Select queries rebuild the form without the
                // editing MenuItem, so excluded ids (self + subtree) are not applied and the item
                // can appear as its own parent. Plain EntityType uses query_builder choices only.
            ]);
        }

        $builder->add('sectionCollapsible', ChoiceType::class, [
            'required'                  => false,
            'label'                     => 'form.menu_item_type.section_collapsible.label',
            'help'                      => 'form.menu_item_type.section_collapsible.help',
            'placeholder'               => $t('form.menu_item_type.section_collapsible.inherit'),
            'choices'                   => [
                'form.menu_item_type.section_collapsible.yes' => true,
                'form.menu_item_type.section_collapsible.no'  => false,
            ],
            'choice_translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
            'attr'                      => ['class' => 'form-select'],
            'row_attr'                  => ['class' => 'mb-1'],
            'label_attr'                => ['class' => 'form-label'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => MenuItem::class,
            'app_routes'         => [],
            'menu'               => null,
            'exclude_ids'        => [],
            'locale'             => $this->defaultLocale,
            'item_form_section'  => null,
            // Passed from MenuItemType: child builder getData() is null during buildForm with inherit_data.
            'menu_item'          => null,
            'constraints' => [
                new Callback($this->validateParentNoCircular(...)),
                new Callback($this->validateSectionMustBeRoot(...)),
                new Callback($this->validateServiceRequiresResolver(...)),
            ],
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setAllowedTypes('app_routes', 'array');
        $resolver->setAllowedTypes('menu', [Menu::class, 'null']);
        $resolver->setAllowedTypes('exclude_ids', 'array');
        $resolver->setAllowedTypes('locale', 'string');
        $resolver->setAllowedTypes('item_form_section', ['string', 'null']);
        $resolver->setAllowedTypes('menu_item', [MenuItem::class, 'null']);
    }

    /**
     * Model for this compound type: with inherit_data the child builder often has no data during
     * buildForm(); MenuItemType sets menu_item, and unit tests may set data on the builder directly.
     *
     * @param array<string, mixed> $options
     */
    private function resolveMenuItemFormData(FormBuilderInterface $builder, array $options): mixed
    {
        $data = $builder->getData();
        if ($data instanceof MenuItem) {
            return $data;
        }

        $fromParent = $options['menu_item'] ?? null;

        return $fromParent instanceof MenuItem ? $fromParent : $data;
    }

    /**
     * Prevent circular parent references:
     * - an item cannot be its own parent
     * - an item cannot be assigned as parent to one of its descendants
     *
     * @param MenuItem $item the menu item being validated
     * @param ExecutionContextInterface $context validation context
     */
    public function validateParentNoCircular(MenuItem $item, ExecutionContextInterface $context): void
    {
        $parent = $item->getParent();
        if (!$parent instanceof MenuItem) {
            return;
        }

        $itemId   = $item->getId();
        $parentId = $parent->getId();
        $menu     = $item->getMenu();
        if ($menu instanceof Menu && $itemId !== null && $parentId !== null) {
            $forbidden = $this->menuItemRepository->findIdsInSubtreeStartingAt($menu, (int) $itemId);
            if (in_array((int) $parentId, $forbidden, true)) {
                $context->buildViolation('form.menu_item_type.parent.circular_violation')
                    ->atPath('parent')
                    ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                    ->addViolation();

                return;
            }
        }

        // Direct cycle: parent is the same node.
        if ($parent === $item) {
            $context->buildViolation('form.menu_item_type.parent.circular_violation')
                ->atPath('parent')
                ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                ->addViolation();

            return;
        }

        // Compare by id (covers detached objects and strict int/string mismatches from forms).
        if ($itemId !== null && $parentId !== null && (int) $itemId === (int) $parentId) {
            $context->buildViolation('form.menu_item_type.parent.circular_violation')
                ->atPath('parent')
                ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                ->addViolation();

            return;
        }

        // Walk upwards from the chosen parent. Stop if we hit $item again (same object, e.g. corrupt
        // chain in memory) or the same persisted id (different object instances for the same row).
        $cursor  = $parent;
        $visited = [];
        while ($cursor instanceof MenuItem) {
            if ($cursor === $item) {
                $context->buildViolation('form.menu_item_type.parent.circular_violation')
                    ->atPath('parent')
                    ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                    ->addViolation();

                return;
            }

            $cid = $cursor->getId();
            if ($itemId !== null && $cid !== null && (int) $cid === (int) $itemId) {
                $context->buildViolation('form.menu_item_type.parent.circular_violation')
                    ->atPath('parent')
                    ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                    ->addViolation();

                return;
            }

            if ($cid !== null) {
                if (isset($visited[(int) $cid])) {
                    // Avoid infinite loops if the DB already contains a cycle.
                    return;
                }
                $visited[(int) $cid] = true;
            }

            $cursor = $cursor->getParent();
        }
    }

    public function validateSectionMustBeRoot(MenuItem $item, ExecutionContextInterface $context): void
    {
        if ($item->getItemType() !== MenuItem::ITEM_TYPE_SECTION) {
            return;
        }
        if ($item->getParent() !== null) {
            $context->buildViolation('form.menu_item_type.parent.section_must_be_root')
                ->atPath('parent')
                ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                ->addViolation();
        }
    }

    public function validateServiceRequiresResolver(MenuItem $item, ExecutionContextInterface $context): void
    {
        if ($item->getItemType() !== MenuItem::ITEM_TYPE_SERVICE) {
            return;
        }
        $resolver = $item->getLinkResolver();
        if ($resolver === null || $resolver === '') {
            $context->buildViolation('form.menu_item_type.link_resolver.required')
                ->atPath('linkResolver')
                ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                ->addViolation();
        }
    }

    /**
     * Breadcrumb for parent dropdown (root &gt; … &gt; item). Safe if DB has a parent cycle (corrupt tree).
     */
    private static function parentChoiceBreadcrumbLabel(MenuItem $item, string $locale): string
    {
        $parts         = [];
        $p             = $item;
        $seenIds       = [];
        $seenObjectIds = [];
        $maxSteps      = 256;
        $step          = 0;
        while ($step < $maxSteps && $p instanceof MenuItem) {
            $id = $p->getId();
            if ($id !== null) {
                $k = (int) $id;
                if (isset($seenIds[$k])) {
                    array_unshift($parts, '…');
                    break;
                }
                $seenIds[$k] = true;
            } else {
                $oid = spl_object_id($p);
                if (isset($seenObjectIds[$oid])) {
                    array_unshift($parts, '…');
                    break;
                }
                $seenObjectIds[$oid] = true;
            }

            array_unshift($parts, $p->getLabelForLocale($locale));
            $next = $p->getParent();
            if ($next === $p) {
                break;
            }
            $p = $next;
            ++$step;
        }

        if ($step >= $maxSteps && $p instanceof MenuItem) {
            array_unshift($parts, '…');
        }

        return implode(' > ', $parts);
    }

    /**
     * Build choices for the permission keys field (label => value). Translates via form.menu_item_type.permission_keys.choice.{safe_key}.
     *
     * @return array<string, string> label => permission key
     */
    private function buildPermissionKeyChoices(Closure $t, mixed $data): array
    {
        $choices = [];
        foreach ($this->permissionKeyChoices as $key) {
            $safe  = str_replace(['/', ':'], '_', $key);
            $trKey = 'form.menu_item_type.permission_keys.choice.' . $safe;
            $label = $t($trKey);

            $choices[$label === $trKey ? $key : $label] = $key;
        }
        if ($data instanceof MenuItem) {
            $currentKeys = $data->getPermissionKeys() ?? [];
            foreach ($currentKeys as $current) {
                if (!in_array($current, $this->permissionKeyChoices, true)) {
                    $choices[$current . ' (current)'] = $current;
                }
            }
        }

        return $choices;
    }

    /**
     * @param array<string, array{label: string, params: list<string>}> $appRoutes
     *
     * @return array<string, string> label => route name
     */
    private function buildRouteChoices(array $appRoutes): array
    {
        $routeChoices = [];
        foreach ($appRoutes as $name => $data) {
            $routeChoices[$data['label']] = $name;
        }

        return $routeChoices;
    }
}
