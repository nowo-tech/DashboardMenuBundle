<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Form\DataTransformer\JsonToArrayTransformer;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Form type for menu definition: code, base, name, context, icon.
 * Shown in the dashboard with a pencil icon (definición / identidad).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuDefinitionType extends AbstractType
{
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data       = $builder->getData();
        $isEdit     = $data instanceof Menu && $data->getId() !== null;
        $isBase     = $data instanceof Menu && $data->isBase();
        $codeLocked = $isEdit && $isBase;
        $t          = fn (string $id): string => $this->translator instanceof TranslatorInterface ? $this->translator->trans($id, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $id;

        $builder
            ->add('code', TextType::class, [
                'required' => true,
                'label'    => 'form.menu_type.code.label',
                'attr'     => [
                    'class'       => 'form-control',
                    'pattern'     => '[a-zA-Z0-9_-]+',
                    'placeholder' => $t('form.menu_type.code.placeholder'),
                    'readonly'    => $codeLocked,
                ],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
                'help'       => 'form.menu_type.code.help',
            ])
            ->add('base', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.base.label',
                'attr'       => ['class' => 'form-check-input'],
                'row_attr'   => ['class' => 'ms-3 mb-1 form-check'],
                'label_attr' => ['class' => 'form-check-label'],
                'help'       => 'form.menu_type.base.help',
            ])
            ->add('name', TextType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.name.label',
                'attr'       => ['class' => 'form-control', 'placeholder' => $t('form.menu_type.name.placeholder')],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('context', TextareaType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.context.label',
                'attr'       => ['class' => 'form-control font-monospace', 'rows' => 3, 'placeholder' => $t('form.menu_type.context.placeholder')],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
                'help'       => 'form.menu_type.context.help',
            ])
            ->add('icon', TextType::class, [
                'required'   => false,
                'label'      => 'form.menu_type.icon.label',
                'attr'       => ['class' => 'form-control', 'placeholder' => $t('form.menu_type.icon.placeholder')],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
                'help'       => 'form.menu_type.icon.help',
            ]);

        $builder->get('context')->addModelTransformer(new JsonToArrayTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => Menu::class,
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
    }
}
