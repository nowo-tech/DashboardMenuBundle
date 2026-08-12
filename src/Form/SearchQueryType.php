<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * GET search form for dashboard filters.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[FormKitConfig('dashboard_menu')]
final class SearchQueryType extends AbstractType
{
    use FormOptionsTrait;

    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator instanceof TranslatorInterface
            ? $this->translator->trans($id, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
            : $id;

        $this->addWithDefaults($builder, 'q', SearchType::class, [
            'required'    => false,
            'label'       => false,
            'help'        => false,
            'empty_data'  => '',
            'placeholder' => $t('dashboard.search_placeholder'),
            'attr'        => [
                'aria-label'   => $t('dashboard.search'),
                'autocomplete' => 'off',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection'    => false,
            'data_class'         => null,
            'method'             => 'GET',
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setDefined(['action']);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
