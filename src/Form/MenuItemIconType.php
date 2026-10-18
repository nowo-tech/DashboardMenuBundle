<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_key_exists;
use function constant;
use function is_array;
use function is_subclass_of;

/**
 * Form type for editing MenuItem identity fields that are tied to "icon identity":
 * itemType + position + icon.
 *
 * @extends AbstractType<MenuItem>
 */
#[FormKitConfig('dashboard_menu')]
final class MenuItemIconType extends AbstractType
{
    use FormOptionsTrait;

    public function __construct(
        private readonly MenuIconNameResolver $menuIconNameResolver,
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

        $this->addWithDefaults($builder, 'itemType', ChoiceType::class, [
            'choices' => [
                'form.menu_item_type.type.link'    => MenuItem::ITEM_TYPE_LINK,
                'form.menu_item_type.type.service' => MenuItem::ITEM_TYPE_SERVICE,
                'form.menu_item_type.type.section' => MenuItem::ITEM_TYPE_SECTION,
                'form.menu_item_type.type.divider' => MenuItem::ITEM_TYPE_DIVIDER,
            ],
            'label'              => 'form.menu_item_type.type.label',
            'autocomplete'       => true,
            'tom_select_options' => NowoDashboardMenuBundle::TOM_SELECT_MODAL_DROPDOWN,
        ]);

        if (!$itemTypeOnly) {
            $this->addWithDefaults($builder, 'position', IntegerType::class, [
                'required'   => false,
                'empty_data' => '0',
                'label'      => 'form.menu_item_type.position.label',
                'attr'       => ['min' => 0],
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

            $iconSelectorType = 'Nowo\\IconSelectorBundle\\Form\\IconSelectorType';
            if (is_subclass_of($iconSelectorType, FormTypeInterface::class)) {
                $this->addWithDefaults($builder, 'icon', $iconSelectorType, [
                    'required'           => false,
                    'mode'               => constant($iconSelectorType . '::MODE_TOM_SELECT'),
                    'label'              => 'form.menu_item_type.icon.label',
                    'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
                    'attr'               => ['placeholder' => 'form.menu_item_type.icon.placeholder'],
                ]);
            } else {
                $this->addWithDefaults($builder, 'icon', TextType::class, [
                    'required'           => false,
                    'label'              => 'form.menu_item_type.icon.label',
                    'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
                    'attr'               => [
                        'placeholder' => 'form.menu_item_type.icon.placeholder',
                    ],
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
