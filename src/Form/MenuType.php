<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Form\DataTransformer\JsonToArrayTransformer;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

use const SORT_NATURAL;

/**
 * Form type for creating/editing a menu.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuType extends AbstractType
{
    /**
     * @param array<string, string> $permissionCheckerChoices Map of service id => label (from tagged permission checkers)
     * @param array<string, list<string>> $cssClassOptions Map of css slot => list of allowed classes (menu, item, link, etc.)
     */
    public function __construct(
        private readonly array $permissionCheckerChoices = [],
        private readonly array $cssClassOptions = [],
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $choices Base map of permission checker service id => label */
        $choices = $this->permissionCheckerChoices;

        $data       = $builder->getData();
        $isEdit     = $data instanceof Menu && $data->getId() !== null;
        $isBase     = $data instanceof Menu && $data->isBase();
        $codeLocked = $isEdit && $isBase;
        if ($data instanceof Menu) {
            $current = $data->getPermissionChecker();
            if ($current !== null && $current !== '' && !isset($choices[$current])) {
                $choices[$current] = $current . ' (current)';
                ksort($choices, SORT_NATURAL);
            }
        }
        $t = fn (string $id): string => $this->translator instanceof TranslatorInterface ? $this->translator->trans($id, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $id;
        $builder
            ->add('code', TextType::class, [
                'required' => true,
                'label'    => 'form.menu_type.code.label',
                'attr'     => [
                    'class'       => 'form-control',
                    'pattern'     => '[a-zA-Z0-9_-]+',
                    'placeholder' => $t('form.menu_type.code.placeholder'),
                    'readonly'    => $codeLocked,
                ],
                'help' => 'form.menu_type.code.help',
            ])
            ->add('base', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.base.label',
                'attr'       => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
                'help'       => 'form.menu_type.base.help',
            ])
            ->add('name', TextType::class, [
                'required' => false,
                'label'    => 'form.menu_type.name.label',
                'attr'     => ['class' => 'form-control', 'placeholder' => $t('form.menu_type.name.placeholder')],
            ])
            ->add('context', TextareaType::class, [
                'required' => false,
                'label'    => 'form.menu_type.context.label',
                'attr'     => ['class' => 'form-control font-monospace', 'rows' => 3, 'placeholder' => $t('form.menu_type.context.placeholder')],
                'help'     => 'form.menu_type.context.help',
            ])
            ->add('icon', TextType::class, [
                'required' => false,
                'label'    => 'form.menu_type.icon.label',
                'attr'     => ['class' => 'form-control', 'placeholder' => $t('form.menu_type.icon.placeholder')],
                'help'     => 'form.menu_type.icon.help',
            ])
            ->add('permissionChecker', ChoiceType::class, [
                'required'     => false,
                'label'        => 'form.menu_type.permission_checker.label',
                'placeholder'  => 'form.menu_type.permission_checker.placeholder',
                'choices'      => $choices,
                'attr'         => ['class' => 'form-select'],
                'autocomplete' => true,
                'help'         => 'form.menu_type.permission_checker.help',
            ])
            ->add('depthLimit', IntegerType::class, [
                'required' => false,
                'label'    => 'form.menu_type.depth_limit.label',
                'attr'     => ['class' => 'form-control', 'min' => 0, 'placeholder' => $t('form.menu_type.depth_limit.placeholder')],
                'help'     => 'form.menu_type.depth_limit.help',
            ])
            ->add('collapsible', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.collapsible.label',
                'attr'       => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ])
            ->add('collapsibleExpanded', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.collapsible_expanded.label',
                'attr'       => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ])
            ->add('nestedCollapsible', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.nested_collapsible.label',
                'attr'       => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ]);
        $builder->add('nestedCollapsibleSections', CheckboxType::class, [
            'required'   => false,
            'label'      => 'form.menu_type.nested_collapsible_sections.label',
            'attr'       => ['class' => 'form-check-input'],
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
        $builder->get('context')->addModelTransformer(new JsonToArrayTransformer());
    }

    /**
     * Adds a CSS class field: ChoiceType with config options when available, otherwise TextType for free text.
     */
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
                'autocomplete'              => true,
            ]);
        } else {
            $placeholderText = $this->translator instanceof TranslatorInterface ? $this->translator->trans($placeholder, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $placeholder;
            $builder->add($fieldName, TextType::class, [
                'required' => false,
                'label'    => $label,
                'attr'     => ['class' => 'form-control', 'placeholder' => $placeholderText],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Menu::class,
            'is_edit'            => false,
            'method'             => 'POST',
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setDefined(['action']);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
