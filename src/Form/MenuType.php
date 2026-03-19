<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for creating/editing a menu.
 * Composes definition (code, base, name, context, icon) and config (permission, depth, collapsible, CSS).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $section = $options['section'] ?? null;

        if ($section !== 'config') {
            $builder->add('definition', MenuDefinitionType::class, [
                'inherit_data' => true,
            ]);
        }
        if ($section !== 'basic') {
            $builder->add('config', MenuConfigType::class, [
                'inherit_data' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Menu::class,
            'is_edit'            => false,
            'method'             => 'POST',
            'section'            => null,
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setDefined(['action']);
        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedValues('section', [null, 'basic', 'config']);
    }
}
