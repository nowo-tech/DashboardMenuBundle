<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for creating/editing a menu item.
 * Composes two sections: basic (type, icon, labels) and config (position, link, parent, permission).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuItemType extends AbstractType
{
    /**
     * Composes the menu item form depending on the requested section:
     * - `basic`/`identity`: renders `basic` + `icon` (no config)
     * - `icon`: renders only the `icon` section
     * - `config`: renders only the `config` section
     * - `null`: renders all sections
     *
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $section  = $options['section'] ?? null;
        $addBasic = in_array($section, [null, 'basic', 'identity'], true);
        $addIcon  = in_array($section, [null, 'icon', 'identity'], true);
        // Identity = basic + icon only (no config).
        $addConfig = $section === null || $section === 'config';

        if ($addBasic) {
            $builder->add('basic', MenuItemBasicType::class, [
                'inherit_data'         => true,
                'available_locales'    => $options['available_locales'],
                'include_translations' => $options['include_translations'],
            ]);
        }

        if ($addIcon) {
            $builder->add('icon', MenuItemIconType::class, [
                'inherit_data' => true,
            ]);
        }

        if ($addConfig) {
            $builder->add('config', MenuItemConfigType::class, [
                'inherit_data' => true,
                'app_routes'   => $options['app_routes'],
                'menu'         => $options['menu'],
                'exclude_ids'  => $options['exclude_ids'],
                'locale'       => $options['locale'],
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
        $resolver->setAllowedValues('section', [null, 'basic', 'icon', 'identity', 'config']);
    }
}
