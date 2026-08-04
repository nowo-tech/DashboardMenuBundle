<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Form to set code and name when copying a menu.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[FormKitConfig('dashboard_menu')]
final class CopyMenuType extends AbstractType
{
    use FormOptionsTrait;

    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator instanceof TranslatorInterface ? $this->translator->trans($id, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $id;

        $this->withBuilder($builder, function () use ($t): void {
            $this->addTextField('code', [
                'required' => true,
                'label'    => 'form.copy_menu_type.code.label',
                'help'     => false,
                'attr'     => [
                    'pattern'     => '[a-zA-Z0-9_-]+',
                    'placeholder' => $t('form.copy_menu_type.code.placeholder'),
                ],
                'constraints' => [
                    new NotBlank(),
                    new Regex(pattern: '#^[a-zA-Z0-9_-]+$#', message: 'form.copy_menu_type.code.regex_message'),
                ],
            ]);
            $this->addTextField('name', [
                'required' => false,
                'label'    => 'form.copy_menu_type.name.label',
                'help'     => false,
                'attr'     => ['placeholder' => $t('form.copy_menu_type.name.placeholder')],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'method'             => 'POST',
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setDefined(['action']);
    }
}
