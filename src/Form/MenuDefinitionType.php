<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Form\DataTransformer\JsonToArrayTransformer;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for menu definition: code, base, name, context, icon.
 * Shown in the dashboard with a pencil icon (definition / identity).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @extends AbstractType<Menu>
 */
#[FormKitConfig('dashboard_menu')]
final class MenuDefinitionType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data       = $builder->getData();
        $isEdit     = $data instanceof Menu && $data->getId() !== null;
        $isBase     = $data instanceof Menu && $data->isBase();
        $codeLocked = $isEdit && $isBase;

        $this->withBuilder($builder, function () use ($codeLocked): void {
            $this->addTextField('code', [
                'required' => true,
                'label'    => 'form.menu_type.code.label',
                'help'     => 'form.menu_type.code.help',
                'attr'     => [
                    'pattern'     => '[a-zA-Z0-9_\\-]+',
                    'placeholder' => 'form.menu_type.code.placeholder',
                    'readonly'    => $codeLocked,
                ],
            ]);
            $this->addCheckboxField('base', [
                'required' => false,
                'label'    => 'form.menu_type.base.label',
                'help'     => 'form.menu_type.base.help',
            ]);
            $this->addTextField('name', [
                'required' => false,
                'label'    => 'form.menu_type.name.label',
                'help'     => false,
                'attr'     => ['placeholder' => 'form.menu_type.name.placeholder'],
            ]);
            $this->addTextareaField('context', [
                'required' => false,
                'label'    => 'form.menu_type.context.label',
                'help'     => 'form.menu_type.context.help',
                'attr'     => [
                    'rows'        => 3,
                    'placeholder' => 'form.menu_type.context.placeholder',
                ],
            ]);
            $this->addTextField('icon', [
                'required' => false,
                'label'    => 'form.menu_type.icon.label',
                'help'     => 'form.menu_type.icon.help',
                'attr'     => ['placeholder' => 'form.menu_type.icon.placeholder'],
            ]);
        });

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
