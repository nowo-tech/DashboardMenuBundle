<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function in_array;

/**
 * Form type for creating/editing a menu item.
 * Composes basic (labels + i18n), icon (type, position, icon), and config (parent, link, permissions, …).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuItemType extends AbstractType
{
    /**
     * Composes the menu item form depending on the requested section:
     * - `basic`: renders `basic` only (labels + translations; pencil edit, or add-child label step)
     * - `minimal`: renders `basic` + `itemType` only (dashboard "new item": label + type; position assigned on save)
     * - `identity`: renders `basic` + `icon` + `config` (add root item: type + link/resolver in one modal)
     * - `icon`: renders `icon` only (type, position, icon)
     * - `config`: renders only the `config` section (gear: parent, link/resolver, permissions, …)
     * - `null`: renders all sections
     *
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data      = $builder->getData();
        $section   = $options['section'] ?? null;
        $addBasic  = in_array($section, [null, 'basic', 'identity', 'minimal'], true);
        $addIcon   = in_array($section, [null, 'icon', 'identity', 'minimal'], true);
        $addConfig = $section !== 'minimal' && ($section === null || $section === 'config' || $section === 'identity');

        if ($addBasic) {
            $builder->add('basic', MenuItemBasicType::class, [
                'inherit_data'         => true,
                'available_locales'    => $options['available_locales'],
                'include_translations' => $options['include_translations'],
            ]);
        }

        if ($addIcon) {
            $builder->add('icon', MenuItemIconType::class, [
                'inherit_data'   => true,
                'item_type_only' => $section === 'minimal',
            ]);
        }

        if ($addConfig) {
            $builder->add('config', MenuItemConfigType::class, [
                'inherit_data' => true,
                'menu_item'    => $data instanceof MenuItem ? $data : null,
                'app_routes'   => $options['app_routes'],
                'menu'         => $options['menu'],
                'exclude_ids'  => $options['exclude_ids'],
                'locale'       => $options['locale'],
                // Only the gear (config-only) partial uses trimmed fields; identity/full need all link fields for JS toggling.
                'item_form_section' => $options['section'] === 'config' ? 'config' : null,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'           => MenuItem::class,
            'app_routes'           => [],
            'menu'                 => null,
            'exclude_ids'          => [],
            'locale'               => 'en',
            'available_locales'    => [],
            'section'              => null,
            'include_translations' => true,
            'method'               => 'POST',
        ]);
        $resolver->setDefined(['action']);
        $resolver->setAllowedTypes('app_routes', 'array');
        $resolver->setAllowedTypes('menu', [\Nowo\DashboardMenuBundle\Entity\Menu::class, 'null']);
        $resolver->setAllowedTypes('exclude_ids', 'array');
        $resolver->setAllowedTypes('locale', 'string');
        $resolver->setAllowedTypes('available_locales', 'array');
        $resolver->setAllowedTypes('include_translations', 'bool');
        $resolver->setAllowedValues('section', [null, 'basic', 'icon', 'identity', 'config', 'minimal']);
    }
}
