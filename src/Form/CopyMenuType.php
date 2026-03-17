<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
final class CopyMenuType extends AbstractType
{
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id): string => $this->translator instanceof TranslatorInterface ? $this->translator->trans($id) : $id;
        $builder
            ->add('code', TextType::class, [
                'required' => true,
                'label'    => 'form.copy_menu_type.code.label',
                'attr'     => [
                    'class'       => 'form-control',
                    'pattern'     => '[a-zA-Z0-9_-]+',
                    'placeholder' => $t('form.copy_menu_type.code.placeholder'),
                ],
                'constraints' => [
                    new NotBlank(),
                    new Regex(pattern: '#^[a-zA-Z0-9_-]+$#', message: 'form.copy_menu_type.code.regex_message'),
                ],
            ])
            ->add('name', TextType::class, [
                'required' => false,
                'label'    => 'form.copy_menu_type.name.label',
                'attr'     => ['class' => 'form-control', 'placeholder' => $t('form.copy_menu_type.name.placeholder')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method'     => 'POST',
        ]);
        $resolver->setDefined(['action']);
    }
}
