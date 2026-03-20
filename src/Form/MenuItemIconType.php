<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function is_array;

/**
 * Form type for editing MenuItem identity fields that are tied to "icon identity":
 * itemType + position + icon.
 */
final class MenuItemIconType extends AbstractType
{
    public function __construct(
        private readonly MenuIconNameResolver $menuIconNameResolver,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * Builds the "icon identity" section for a menu item:
     * - `itemType` (link/section/divider)
     * - `position` (sibling ordering)
     * - `icon` (optional icon string; uses IconSelectorType when available)
     *
     * The section includes defensive handling to normalize empty `position`
     * values to `0`, so Symfony can safely map the value to the entity setter.
     *
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $builder->getData();
        $icon = $data instanceof MenuItem ? $data->getIcon() : null;
        // icon-selector-bundle validates submitted values against its allowed icon IDs.
        // We normalize only the initial value to the short prefix expected by the selector
        // (e.g. "bootstrap-icons:house" -> "bi:house") so the ChoiceType doesn't fail.
        $normalizedIcon = $this->menuIconNameResolver->resolve($icon);

        $t = fn (string $id): string => $this->translator instanceof TranslatorInterface
            ? $this->translator->trans($id, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
            : $id;

        $builder
            ->add('itemType', ChoiceType::class, [
                'choices' => [
                    'form.menu_item_type.type.link'    => MenuItem::ITEM_TYPE_LINK,
                    'form.menu_item_type.type.section' => MenuItem::ITEM_TYPE_SECTION,
                    'form.menu_item_type.type.divider' => MenuItem::ITEM_TYPE_DIVIDER,
                ],
                'label'        => 'form.menu_item_type.type.label',
                'attr'         => ['class' => 'form-select'],
                'row_attr'     => ['class' => 'mb-1'],
                'label_attr'   => ['class' => 'form-label'],
                'autocomplete' => true,
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                // Avoid null mapping to MenuItem::setPosition(int).
                'empty_data' => 0,
                'label'      => 'form.menu_item_type.position.label',
                'attr'       => ['min' => 0, 'class' => 'form-control'],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
            ]);

        // Defensive: if "position" comes as null/empty (e.g. field not submitted),
        // normalize it to an int before Symfony maps it to the entity setter.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            // Do NOT inject `position` when the key is missing from the payload.
            // If the field is absent (e.g. icon section hidden), setting it here would
            // overwrite the entity value with `0`.
            if (array_key_exists('position', $data) && ($data['position'] === null || $data['position'] === '')) {
                $data['position'] = 0;
                $event->setData($data);
            }
        });

        if (class_exists('Nowo\\IconSelectorBundle\\Form\\IconSelectorType')) {
            $builder->add('icon', \Nowo\IconSelectorBundle\Form\IconSelectorType::class, [
                // 'mapped'   => true,
                'required' => false,
                // Icon is optional: remove any default NotBlank/required constraints.
                // 'constraints'        => [],
                'mode'               => \Nowo\IconSelectorBundle\Form\IconSelectorType::MODE_TOM_SELECT,
                'label'              => 'form.menu_item_type.icon.label',
                'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
                'attr'               => ['placeholder' => $t('form.menu_item_type.icon.placeholder')],
                'row_attr'           => ['class' => 'mb-1'],
                'label_attr'         => ['class' => 'form-label'],
                // Preselect normalized value so ChoiceType validation accepts it.
                // 'data' => $normalizedIcon,
            ]);
        } else {
            $builder->add('icon', TextType::class, [
                'required'           => false,
                'label'              => 'form.menu_item_type.icon.label',
                'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
                'attr'               => [
                    'class'       => 'form-control',
                    'placeholder' => $t('form.menu_item_type.icon.placeholder'),
                ],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => MenuItem::class,
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
    }
}
