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
     * - `itemType` (link/section/divider/service)
     * - optionally `position` (sibling ordering) and `icon` unless `item_type_only` is true (create flow: label + type only)
     *
     * The section includes defensive handling to normalize empty `position`
     * values to `0`, so Symfony can safely map the value to the entity setter.
     *
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $itemTypeOnly = (bool) ($options['item_type_only'] ?? false);

        $data = $builder->getData();
        if (!$itemTypeOnly) {
            $icon = $data instanceof MenuItem ? $data->getIcon() : null;
            // icon-selector-bundle validates submitted values against its allowed icon IDs.
            $this->menuIconNameResolver->resolve($icon);
        }

        $builder
            ->add('itemType', ChoiceType::class, [
                'choices' => [
                    'form.menu_item_type.type.link'    => MenuItem::ITEM_TYPE_LINK,
                    'form.menu_item_type.type.service' => MenuItem::ITEM_TYPE_SERVICE,
                    'form.menu_item_type.type.section' => MenuItem::ITEM_TYPE_SECTION,
                    'form.menu_item_type.type.divider' => MenuItem::ITEM_TYPE_DIVIDER,
                ],
                'label'              => 'form.menu_item_type.type.label',
                'attr'               => ['class' => 'form-select'],
                'row_attr'           => ['class' => 'mb-1'],
                'label_attr'         => ['class' => 'form-label'],
                'autocomplete'       => true,
                'tom_select_options' => NowoDashboardMenuBundle::TOM_SELECT_MODAL_DROPDOWN,
            ]);

        if (!$itemTypeOnly) {
            $builder
                ->add('position', IntegerType::class, [
                    'required'   => false,
                    'empty_data' => '0',
                    'label'      => 'form.menu_item_type.position.label',
                    'attr'       => ['min' => 0, 'class' => 'form-control'],
                    'row_attr'   => ['class' => 'mb-1'],
                    'label_attr' => ['class' => 'form-label'],
                ]);

            $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
                $data = $event->getData();
                if (!is_array($data)) {
                    return;
                }

                if (array_key_exists('position', $data) && ($data['position'] === null || $data['position'] === '')) {
                    $data['position'] = '0';
                    $event->setData($data);
                }
            });
        }

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!$data instanceof MenuItem) {
                return;
            }
            if ($data->getItemType() === MenuItem::ITEM_TYPE_SECTION) {
                $data->setParent(null);
            }
        });

        if (!$itemTypeOnly) {
            $t = fn (string $id): string => $this->translator instanceof TranslatorInterface
                ? $this->translator->trans($id, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                : $id;

            if (class_exists('Nowo\\IconSelectorBundle\\Form\\IconSelectorType')) {
                $builder->add('icon', \Nowo\IconSelectorBundle\Form\IconSelectorType::class, [
                    'required'           => false,
                    'mode'               => \Nowo\IconSelectorBundle\Form\IconSelectorType::MODE_TOM_SELECT,
                    'label'              => 'form.menu_item_type.icon.label',
                    'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
                    'attr'               => ['placeholder' => $t('form.menu_item_type.icon.placeholder')],
                    'row_attr'           => ['class' => 'mb-1'],
                    'label_attr'         => ['class' => 'form-label'],
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => MenuItem::class,
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
            'item_type_only'     => false,
        ]);
        $resolver->setAllowedTypes('item_type_only', 'bool');
    }
}
