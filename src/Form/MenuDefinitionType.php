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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Form type for menu definition: code, base, name, context, icon.
 * Shown in the dashboard with a pencil icon (definition / identity).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[FormKitConfig('dashboard_menu')]
final class MenuDefinitionType extends AbstractType
{
    use FormOptionsTrait;

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

        $this->withBuilder($builder, function () use ($t, $codeLocked): void {
            $this->addTextField('code', [
                'required' => true,
                'label'    => 'form.menu_type.code.label',
                'help'     => 'form.menu_type.code.help',
                'attr'     => [
                    'pattern'     => '[a-zA-Z0-9_-]+',
                    'placeholder' => $t('form.menu_type.code.placeholder'),
                    'readonly'    => $codeLocked,
                ],
            ]);
            $this->addCheckboxField('base', [
                'required'   => false,
                'label'      => 'form.menu_type.base.label',
                'help'       => 'form.menu_type.base.help',
                'row_attr'   => ['class' => 'ms-3 mb-1 form-check'],
                'label_attr' => ['class' => 'form-check-label'],
            ]);
            $this->addTextField('name', [
                'required' => false,
                'label'    => 'form.menu_type.name.label',
                'help'     => false,
                'attr'     => ['placeholder' => $t('form.menu_type.name.placeholder')],
            ]);
            $this->addTextareaField('context', [
                'required' => false,
                'label'    => 'form.menu_type.context.label',
                'help'     => 'form.menu_type.context.help',
                'attr'     => [
                    'class'       => 'nowo-ui-input form-control font-monospace',
                    'rows'        => 3,
                    'placeholder' => $t('form.menu_type.context.placeholder'),
                ],
            ]);
            $this->addTextField('icon', [
                'required' => false,
                'label'    => 'form.menu_type.icon.label',
                'help'     => 'form.menu_type.icon.help',
                'attr'     => ['placeholder' => $t('form.menu_type.icon.placeholder')],
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
