<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Form to upload a JSON file and choose import strategy.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[FormKitConfig('dashboard_menu')]
final class ImportMenuType extends AbstractType
{
    use FormOptionsTrait;

    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $id, array $params = []): string => $this->translator instanceof TranslatorInterface
            ? $this->translator->trans($id, $params, NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $id;

        $this->addWithDefaults($builder, 'file', FileType::class, [
            'required'    => true,
            'label'       => $t('form.import_menu_type.file.label'),
            'help'        => false,
            'placeholder' => false,
            'attr'        => ['accept' => '.json,application/json'],
            'constraints' => [
                new NotBlank(message: $t('form.import_menu_type.file.required')),
                new File(
                    maxSize: '2M',
                    mimeTypes: ['application/json', 'text/plain'],
                    mimeTypesMessage: $t('form.import_menu_type.file.mime_message'),
                ),
            ],
        ]);
        $this->addWithDefaults($builder, 'strategy', ChoiceType::class, [
            'required'    => true,
            'label'       => $t('form.import_menu_type.strategy.label'),
            'help'        => false,
            'placeholder' => false,
            'choices'     => [
                $t('form.import_menu_type.strategy.skip_existing') => MenuImporter::STRATEGY_SKIP_EXISTING,
                $t('form.import_menu_type.strategy.replace')       => MenuImporter::STRATEGY_REPLACE,
            ],
            'data' => MenuImporter::STRATEGY_SKIP_EXISTING,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'POST',
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
    }
}
