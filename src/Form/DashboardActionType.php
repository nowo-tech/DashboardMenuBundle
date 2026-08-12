<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Root-level CSRF-only form for dashboard POST actions.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[FormKitConfig('dashboard_menu')]
final class DashboardActionType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_field_name'    => '_token',
            'csrf_protection'    => true,
            'data_class'         => null,
            'method'             => 'POST',
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setDefined(['action']);
        $resolver->setRequired(['csrf_token_id']);
        $resolver->setAllowedTypes('csrf_token_id', 'string');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
