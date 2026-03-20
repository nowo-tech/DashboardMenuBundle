<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\IconSelectorBundle\Form\IconSelectorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_array;

/**
 * Form type for menu item basics: type, icon, label and per-locale labels.
 * Shown in the dashboard with a pencil icon (edición / identidad).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuItemBasicType extends AbstractType
{
    /**
     * @param list<string> $availableLocales
     */
    public function __construct(
        private readonly array $availableLocales = [],
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $availableLocales */
        $availableLocales = $options['available_locales'];
        $t                = fn (string $id): string => $this->translator instanceof TranslatorInterface ? $this->translator->trans($id, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $id;

        $builder
            ->add('label', TextType::class, [
                'required'   => false,
                'label'      => 'form.menu_item_type.label.label',
                'attr'       => ['class' => 'form-control'],
                'row_attr'   => ['class' => 'mb-1'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('itemType', ChoiceType::class, [
                'required' => false,
                'choices'  => [
                    'form.menu_item_type.type.link'    => MenuItem::ITEM_TYPE_LINK,
                    'form.menu_item_type.type.section' => MenuItem::ITEM_TYPE_SECTION,
                    'form.menu_item_type.type.divider' => MenuItem::ITEM_TYPE_DIVIDER,
                ],
                'label'        => 'form.menu_item_type.type.label',
                'attr'         => ['class' => 'form-select'],
                'row_attr'     => ['class' => 'mb-1'],
                'label_attr'   => ['class' => 'form-label'],
                'autocomplete' => true,
            ]);

        if (class_exists('Nowo\IconSelectorBundle\Form\IconSelectorType')) {
            $builder->add('icon', IconSelectorType::class, [
                'required'           => false,
                'mode'               => IconSelectorType::MODE_TOM_SELECT,
                'label'              => 'form.menu_item_type.icon.label',
                'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
                'attr'               => ['placeholder' => $t('form.menu_item_type.icon.placeholder')],
                'row_attr'           => ['class' => 'mb-1'],
                'label_attr'         => ['class' => 'form-label'],
            ]);
        } else {
            $builder->add('icon', TextType::class, [
                'required'           => false,
                'label'              => 'form.menu_item_type.icon.label',
                'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
                'attr'               => ['class' => 'form-control', 'placeholder' => $t('form.menu_item_type.icon.placeholder')],
                'row_attr'           => ['class' => 'mb-1'],
                'label_attr'         => ['class' => 'form-label'],
            ]);
        }

        if ($availableLocales !== []) {
            $data         = $builder->getData();
            $translations = $data instanceof MenuItem ? ($data->getTranslations() ?? []) : [];
            foreach ($availableLocales as $locale) {
                $fieldName = 'label_' . $locale;
                $builder->add($fieldName, TextType::class, [
                    'required'                     => false,
                    'mapped'                       => false,
                    'label'                        => 'form.menu_item_type.label_locale',
                    'label_translation_parameters' => ['%locale%' => $locale],
                    'data'                         => $translations[$locale] ?? null,
                    'attr'                         => ['class' => 'form-control'],
                    'row_attr'                     => ['class' => 'mb-1'],
                    'label_attr'                   => ['class' => 'form-label'],
                ]);
            }
            $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) use ($availableLocales): void {
                $data = $event->getData();
                if (!$data instanceof MenuItem) {
                    $data = $event->getForm()->getParent()?->getData();
                }
                if (!$data instanceof MenuItem) {
                    return;
                }
                $form         = $event->getForm();
                $translations = $data->getTranslations() ?? [];
                foreach ($availableLocales as $locale) {
                    $fieldName = 'label_' . $locale;
                    if (!$form->has($fieldName)) {
                        continue;
                    }
                    $value = $form->get($fieldName)->getData();
                    if ($value === null || $value === '') {
                        unset($translations[$locale]);
                    } else {
                        $translations[$locale] = (string) $value;
                    }
                }
                $data->setTranslations($translations === [] ? null : $translations);
                $event->setData($data);
            });
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($availableLocales): void {
            $data = $event->getData();
            if (!is_array($data) || ($data['itemType'] ?? '') !== MenuItem::ITEM_TYPE_DIVIDER) {
                return;
            }
            $data['label'] = '';
            $data['icon']  = null;
            foreach ($availableLocales as $locale) {
                $data['label_' . $locale] = null;
            }
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => MenuItem::class,
            'available_locales'  => $this->availableLocales,
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
            'constraints'        => [
                new Callback($this->validateLabelWhenNotDivider(...)),
            ],
        ]);
        $resolver->setAllowedTypes('available_locales', 'array');
    }

    public function validateLabelWhenNotDivider(MenuItem $item, ExecutionContextInterface $context): void
    {
        if ($item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
            return;
        }
        if (trim($item->getLabel()) === '') {
            $context->buildViolation('form.menu_item_type.label_required')
                ->atPath('label')
                ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                ->addViolation();
        }
    }
}
