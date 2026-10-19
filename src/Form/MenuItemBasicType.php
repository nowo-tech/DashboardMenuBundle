<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function in_array;

/**
 * Form type for menu item labels: label + per-locale translations.
 * Shown in the dashboard with a pencil icon (edit / identity).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @extends AbstractType<MenuItem|null>
 */
#[FormKitConfig('dashboard_menu')]
final class MenuItemBasicType extends AbstractType
{
    use FormOptionsTrait;

    /**
     * @param list<string> $availableLocales
     * @param string|null $defaultLocale default-language tab of the label locale tabs (null = first available locale)
     */
    public function __construct(
        private readonly array $availableLocales = [],
        private readonly ?string $defaultLocale = null,
    ) {
    }

    /**
     * Builds the "basic/labels" section:
     * - Always: `label` (base label)
     * - Optionally: `label_{locale}` fields when `available_locales` is enabled
     *
     * The per-locale fields are `mapped=false`, and their values are transferred into
     * `MenuItem::setTranslations()` on form submit to keep partial sections consistent.
     *
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $includeTranslations = $options['include_translations'] ?? true;
        /** @var list<string> $availableLocales */
        $availableLocales = $includeTranslations ? $options['available_locales'] : [];

        $this->addWithDefaults($builder, 'label', TextType::class, [
            'required' => false,
            'label'    => 'form.menu_item_type.label.label',
        ])
        ;

        if ($availableLocales !== []) {
            $data         = $builder->getData();
            $translations = $data instanceof MenuItem ? ($data->getTranslations() ?? []) : [];
            foreach ($availableLocales as $locale) {
                $fieldName = 'label_' . $locale;
                $this->addWithDefaults($builder, $fieldName, TextType::class, [
                    'required'                     => false,
                    'mapped'                       => false,
                    'label'                        => 'form.menu_item_type.label_locale',
                    'label_translation_parameters' => ['%locale%' => $locale],
                    'data'                         => $translations[$locale] ?? null,
                ]);
            }

            $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) use ($availableLocales): void {
                $data = $event->getData();
                if (!$data instanceof MenuItem) {
                    // inherit_data + LiveComponent: event data may not be the entity; root form holds the MenuItem.
                    $rootData = $event->getForm()->getRoot()->getData();
                    $data     = $rootData instanceof MenuItem ? $rootData : null;
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
                    $localeField = $form->get($fieldName);
                    // In LiveComponent submissions it may happen that some fields are not included
                    // in the payload; don't treat "not submitted" as "empty" (which would wipe translations).
                    if (!$localeField->isSubmitted()) {
                        continue;
                    }
                    $value = $localeField->getData();
                    if ($value === null || $value === '') {
                        unset($translations[$locale]);
                    } else {
                        $translations[$locale] = (string) $value;
                    }
                }
                $data->setTranslations($translations === [] ? null : $translations);
            });
        }

    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /**
         * Hydrates the initial values of `label_{locale}` fields for the rendered view.
         * This is important when the bundle is editing a partial section and Symfony would
         * otherwise keep stale view data around.
         */
        /** @var list<string> $availableLocales */
        $availableLocales = $options['available_locales'] ?? [];
        if ($availableLocales === []) {
            return;
        }

        // Locale tabs (2.9): the templates render the label_<locale> rows with the FormKit include in `fields` mode.
        $view->vars['locale_fields']  = ['label'];
        $view->vars['default_locale'] = $this->defaultLocale !== null && in_array($this->defaultLocale, $availableLocales, true)
            ? $this->defaultLocale
            : $availableLocales[0];

        if (($options['live_model'] ?? false) === true) {
            // Live Component (2.9): every label_<locale> input carries its own data-model binding, so the locale tabs
            // include (FormKit `fields` mode) can render the rows without per-field Twig attributes.
            foreach ($availableLocales as $locale) {
                $child = $view->children['label_' . $locale] ?? null;
                if ($child instanceof FormView) {
                    $child->vars['attr']['data-model'] = 'on(change)|' . self::modelPath((string) $child->vars['full_name']);
                }
            }
        }

        $data = $form->getData();
        if (!$data instanceof MenuItem) {
            return;
        }

        $translations = $data->getTranslations() ?? [];

        foreach ($availableLocales as $locale) {
            $fieldName = 'label_' . $locale;
            // If the user has already typed a value in the LiveComponent, prefer that value.
            // Otherwise, fallback to the entity stored translations.
            $value = null;
            if ($form->has($fieldName)) {
                $current = $form->get($fieldName)->getData();
                if ($current !== null && $current !== '') {
                    $value = (string) $current;
                }
            }
            $value ??= $translations[$locale] ?? null;

            if (isset($view->children[$fieldName])) {
                $view->children[$fieldName]->vars['value'] = $value;
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'           => MenuItem::class,
            'available_locales'    => $this->availableLocales,
            'include_translations' => true,
            'live_model'           => false,
            'translation_domain'   => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
            'constraints'          => [
                new Callback($this->validateLabelWhenNotDivider(...)),
            ],
        ]);
        $resolver->setAllowedTypes('available_locales', 'array');
        $resolver->setAllowedTypes('include_translations', 'bool');
        $resolver->setAllowedTypes('live_model', 'bool');
    }

    /** `menu_item[basic][label_es]` → `menu_item.basic.label_es` (Live Component model path). */
    private static function modelPath(string $fullName): string
    {
        return str_replace(['][', '[', ']'], ['.', '.', ''], $fullName);
    }

    public function validateLabelWhenNotDivider(MenuItem $item, ExecutionContextInterface $context): void
    {
        /*
         * Divider items have no label.
         * For other item types, the dashboard may store the user-facing label in:
         * - `MenuItem::$label` (base label), or
         * - `MenuItem::$translations` (per-locale JSON)
         *
         * When editing partial sections, `label_{locale}` fields may be hidden, so we accept
         * either a non-empty base label or at least one non-empty translation value.
         */
        if ($item->getItemType() === MenuItem::ITEM_TYPE_DIVIDER) {
            return;
        }
        $baseLabel = trim($item->getLabel());
        if ($baseLabel !== '') {
            return;
        }

        // The dashboard may store the actual user-facing label only in translations (JSON),
        // leaving the base `$label` empty. When editing partial sections (e.g. only icon),
        // the label inputs may be hidden and Symfony will still validate the form.
        $translations      = $item->getTranslations() ?? [];
        $hasAnyTranslation = false;
        foreach ($translations as $v) {
            if (trim($v) !== '') {
                $hasAnyTranslation = true;
                break;
            }
        }

        if (!$hasAnyTranslation) {
            $context->buildViolation('form.menu_item_type.label_required')
                ->atPath('label')
                ->setTranslationDomain(NowoDashboardMenuBundle::TRANSLATION_DOMAIN)
                ->addViolation();
        }
    }
}
