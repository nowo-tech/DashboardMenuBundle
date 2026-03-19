<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

use const SORT_NATURAL;

/**
 * Form type for menu configuration: permission checker, depth, collapsible options, CSS classes.
 * Shown in the dashboard with a gear icon (configuración).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuConfigType extends AbstractType
{
    /**
     * @param array<string, string> $permissionCheckerChoices
     * @param array<string, list<string>> $cssClassOptions
     */
    public function __construct(
        private readonly array $permissionCheckerChoices = [],
        private readonly array $cssClassOptions = [],
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = $this->permissionCheckerChoices;
        $data    = $builder->getData();
        if ($data instanceof Menu) {
            $current = $data->getPermissionChecker();
            if ($current !== null && $current !== '' && !isset($choices[$current])) {
                $choices[$current] = $current . ' (current)';
                ksort($choices, SORT_NATURAL);
            }
        }
        $t = fn (string $id): string => $this->translator instanceof TranslatorInterface ? $this->translator->trans($id, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $id;

        $builder
            ->add('permissionChecker', ChoiceType::class, [
                'required'                  => false,
                'label'                     => 'form.menu_type.permission_checker.label',
                'placeholder'               => 'form.menu_type.permission_checker.placeholder',
                'choices'                   => $choices,
                'choice_translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
                'attr'                      => ['class' => 'form-select'],
                'row_attr'                  => ['class' => 'mb-1'],
                'label_attr'                => ['class' => 'form-label'],
                'autocomplete'              => true,
                'help'                      => 'form.menu_type.permission_checker.help',
            ])
            ->add('depthLimit', IntegerType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.depth_limit.label',
                'attr'       => ['class' => 'form-control', 'min' => 0, 'placeholder' => $t('form.menu_type.depth_limit.placeholder')],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
                'help'       => 'form.menu_type.depth_limit.help',
            ])
            ->add('collapsible', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.collapsible.label',
                'attr'       => ['class' => 'form-check-input'],
                'row_attr'   => ['class' => 'ms-3 mb-1 form-check'],
                'label_attr' => ['class' => 'form-check-label'],
            ])
            ->add('collapsibleExpanded', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.collapsible_expanded.label',
                'attr'       => ['class' => 'form-check-input'],
                'row_attr'   => ['class' => 'ms-3 mb-1 form-check'],
                'label_attr' => ['class' => 'form-check-label'],
            ])
            ->add('nestedCollapsible', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.nested_collapsible.label',
                'attr'       => ['class' => 'form-check-input'],
                'row_attr'   => ['class' => 'ms-3 mb-1 form-check'],
                'label_attr' => ['class' => 'form-check-label'],
            ])
            ->add('nestedCollapsibleSections', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.nested_collapsible_sections.label',
                'attr'       => ['class' => 'form-check-input'],
                'row_attr'   => ['class' => 'ms-3 mb-1 form-check'],
                'label_attr' => ['class' => 'form-check-label'],
                'help'       => 'form.menu_type.nested_collapsible_sections.help',
            ]);

        $this->addCssClassField($builder, 'classMenu', 'menu', 'form.menu_type.class_menu.label', 'form.menu_type.class_menu.placeholder');
        $this->addCssClassField($builder, 'classItem', 'item', 'form.menu_type.class_item.label', 'form.menu_type.class_item.placeholder');
        $this->addCssClassField($builder, 'classLink', 'link', 'form.menu_type.class_link.label', 'form.menu_type.class_link.placeholder');
        $this->addCssClassField($builder, 'classChildren', 'children', 'form.menu_type.class_children.label', 'form.menu_type.class_children.placeholder');
        $this->addCssClassField($builder, 'classSectionLabel', 'section_label', 'form.menu_type.class_section_label.label', 'form.menu_type.class_section_label.placeholder');
        $this->addCssClassField($builder, 'classCurrent', 'current', 'form.menu_type.class_current.label', 'form.menu_type.class_current.placeholder');
        $this->addCssClassField($builder, 'classBranchExpanded', 'branch_expanded', 'form.menu_type.class_branch_expanded.label', 'form.menu_type.class_branch_expanded.placeholder');
        $this->addCssClassField($builder, 'classHasChildren', 'has_children', 'form.menu_type.class_has_children.label', 'form.menu_type.class_has_children.placeholder');
        $this->addCssClassField($builder, 'classExpanded', 'expanded', 'form.menu_type.class_expanded.label', 'form.menu_type.class_expanded.placeholder');
        $this->addCssClassField($builder, 'classCollapsed', 'collapsed', 'form.menu_type.class_collapsed.label', 'form.menu_type.class_collapsed.placeholder');
    }

    private function addCssClassField(
        FormBuilderInterface $builder,
        string $fieldName,
        string $configKey,
        string $label,
        string $placeholder,
    ): void {
        $options = $this->cssClassOptions[$configKey] ?? [];

        if ($options !== []) {
            /** @var array<string, string> $choices */
            $choices = array_combine($options, $options);
            $data    = $builder->getData();
            if ($data instanceof Menu) {
                $getter = 'get' . ucfirst($fieldName);
                if (method_exists($data, $getter)) {
                    $current = $data->$getter();
                    if ($current !== null && $current !== '' && !isset($choices[$current])) {
                        $choices[$current] = $current . ' (current)';
                        ksort($choices, SORT_NATURAL);
                    }
                }
            }
            $emptyKey    = 'form.menu_type.empty_choice';
            $placeholder = $this->translator instanceof TranslatorInterface ? $this->translator->trans($emptyKey, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $emptyKey;
            $builder->add($fieldName, ChoiceType::class, [
                'required'                  => false,
                'label'                     => $label,
                'placeholder'               => $placeholder,
                'choices'                   => $choices,
                'choice_translation_domain' => false,
                'attr'                      => ['class' => 'form-select'],
                'row_attr'                  => ['class' => 'mb-1'],
                'label_attr'                => ['class' => 'form-label'],
                'autocomplete'              => true,
            ]);
        } else {
            $placeholderText = $this->translator instanceof TranslatorInterface ? $this->translator->trans($placeholder, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $placeholder;
            $builder->add($fieldName, TextType::class, [
                'required'   => false,
                'label'      => $label,
                'attr'       => ['class' => 'form-control', 'placeholder' => $placeholderText],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Menu::class,
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
    }
}
