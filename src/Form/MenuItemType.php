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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $section = $options['section'] ?? null;

        if ($section !== 'config') {
            $builder->add('basic', MenuItemBasicType::class, [
                'inherit_data'      => true,
                'available_locales' => $options['available_locales'],
            ]);
        }
        if ($section !== 'basic') {
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
            'data_class'        => MenuItem::class,
            'app_routes'        => [],
            'menu'              => null,
            'exclude_ids'       => [],
            'locale'            => 'en',
            'available_locales' => [],
            'section'           => null,
            'method'            => 'POST',
        ]);
        $resolver->setDefined(['action']);
        $resolver->setAllowedTypes('app_routes', 'array');
        $resolver->setAllowedTypes('menu', [\Nowo\DashboardMenuBundle\Entity\Menu::class, 'null']);
        $resolver->setAllowedTypes('exclude_ids', 'array');
        $resolver->setAllowedTypes('locale', 'string');
        $resolver->setAllowedTypes('available_locales', 'array');
        $resolver->setAllowedValues('section', [null, 'basic', 'config']);
    }
}
