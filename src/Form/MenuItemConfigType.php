<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Closure;
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

use function in_array;

/**
 * Form type for menu item configuration: parent, link (route / external URL), target, permission.
 * Shown in the dashboard with a gear icon (configuration).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuItemConfigType extends AbstractType
{
    /**
     * @param list<string> $permissionKeyChoices
     */
    public function __construct(
        private readonly MenuItemRepository $menuItemRepository,
        private readonly array $permissionKeyChoices = [],
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

        $builder
            ->add('linkType', ChoiceType::class, [
                'choices' => [
                    'form.menu_item_type.link_type.route'        => MenuItem::LINK_TYPE_ROUTE,
                    'form.menu_item_type.link_type.external_url' => MenuItem::LINK_TYPE_EXTERNAL,
                ],
                'label' => 'form.menu_item_type.link_type.label',
                // 'required'     => false,
                'attr'         => ['class' => 'form-select'],
                'row_attr'     => ['class' => 'mb-1'],
                'label_attr'   => ['class' => 'form-label'],
                'autocomplete' => true,
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
                'autocomplete' => true,
            ])
            ->add('routeParams', TextType::class, [
                'required'   => false,
                'label'      => 'form.menu_item_type.route_params.label',
                'attr'       => ['class' => 'form-control font-monospace', 'placeholder' => $t('form.menu_item_type.route_params.placeholder')],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('externalUrl', UrlType::class, [
                'required'   => false,
                'label'      => 'form.menu_item_type.external_url.label',
                'attr'       => ['class' => 'form-control', 'placeholder' => $t('form.menu_item_type.external_url.placeholder')],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('targetBlank', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_item_type.target_blank.label',
                'attr'       => ['class' => 'form-check-input'],
                'row_attr'   => ['class' => 'ms-3 mb-1 form-check'],
                'label_attr' => ['class' => 'form-check-label'],
            ]);

        $permissionKeyOptions = [
            'required'   => false,
            'label'      => 'form.menu_item_type.permission_key.label',
            'row_attr'   => ['class' => 'mb-1'],
            'label_attr' => ['class' => 'form-label'],
        ];
        if ($this->permissionKeyChoices !== []) {
            $choices                                           = $this->buildPermissionKeyChoices($t, $builder->getData());
            $permissionKeyOptions['choices']                   = $choices;
            $permissionKeyOptions['placeholder']               = $t('form.menu_item_type.permission_key.placeholder');
            $permissionKeyOptions['choice_translation_domain'] = false;
            $permissionKeyOptions['attr']                      = ['class' => 'form-select'];
            $permissionKeyOptions['autocomplete']              = true;
            $builder->add('permissionKey', ChoiceType::class, $permissionKeyOptions);
        } else {
            $permissionKeyOptions['attr'] = ['class' => 'form-control'];
            $builder->add('permissionKey', TextType::class, $permissionKeyOptions);
        }

        $builder->get('routeParams')->addModelTransformer(new JsonToArrayTransformer());

        $menu = $options['menu'];
        if ($menu instanceof Menu) {
            $locale     = $options['locale'];
            $excludeIds = $options['exclude_ids'];
            $qb         = $this->menuItemRepository->getPossibleParentsQueryBuilder($menu, $excludeIds);
            $builder->add('parent', EntityType::class, [
                'class'         => MenuItem::class,
                'query_builder' => $qb,
                'choice_label'  => static function (MenuItem $item) use ($locale): string {
                    $parts = [];
                    $p     = $item;
                    while ($p instanceof MenuItem) {
                        array_unshift($parts, $p->getLabelForLocale($locale));
                        $p = $p->getParent();
                    }

                    return implode(' > ', $parts);
                },
                'placeholder'  => $t('form.menu_item_type.parent.placeholder'),
                'required'     => false,
                'label'        => 'form.menu_item_type.parent.label',
                'attr'         => ['class' => 'form-select'],
                'row_attr'     => ['class' => 'mb-1'],
                'label_attr'   => ['class' => 'form-label'],
                'autocomplete' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'  => MenuItem::class,
            'app_routes'  => [],
            'menu'        => null,
            'exclude_ids' => [],
            'locale'      => $this->defaultLocale,
            'constraints' => [
                new Callback($this->validateParentNoCircular(...)),
            ],
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setAllowedTypes('app_routes', 'array');
        $resolver->setAllowedTypes('menu', [Menu::class, 'null']);
        $resolver->setAllowedTypes('exclude_ids', 'array');
        $resolver->setAllowedTypes('locale', 'string');
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

        // Direct cycle: parent is the same node.
        if ($parent === $item) {
            $context->buildViolation('form.menu_item_type.parent.circular_violation')
                ->atPath('parent')
                ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                ->addViolation();

            return;
        }

        // Compare by id (covers detached objects).
        $itemId   = $item->getId();
        $parentId = $parent->getId();
        if ($itemId !== null && $parentId !== null && $itemId === $parentId) {
            $context->buildViolation('form.menu_item_type.parent.circular_violation')
                ->atPath('parent')
                ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                ->addViolation();

            return;
        }

        // Walk upwards from the chosen parent. If we reach $item, the new parent
        // would create a cycle.
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
            if ($cid !== null) {
                if (isset($visited[$cid])) {
                    // Avoid infinite loops if the DB already contains a cycle.
                    return;
                }
                $visited[$cid] = true;
            }

            $cursor = $cursor->getParent();
        }
    }

    /**
     * Build choices for the permission key field (label => value). Translates via form.menu_item_type.permission_key.choice.{safe_key}.
     *
     * @return array<string, string> label => permission key
     */
    private function buildPermissionKeyChoices(Closure $t, mixed $data): array
    {
        $choices = [];
        foreach ($this->permissionKeyChoices as $key) {
            $safe  = str_replace(['/', ':'], '_', $key);
            $trKey = 'form.menu_item_type.permission_key.choice.' . $safe;
            $label = $t($trKey);

            $choices[$label === $trKey ? $key : $label] = $key;
        }
        if ($data instanceof MenuItem) {
            $current = $data->getPermissionKey();
            if ($current !== null && $current !== '' && !in_array($current, $this->permissionKeyChoices, true)) {
                $choices[$current . ' (current)'] = $current;
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
