<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Form\DataTransformer\JsonToArrayTransformer;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\IconSelectorBundle\Form\IconSelectorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Form type for creating/editing a menu item.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MenuItemType extends AbstractType
{
    /**
     * @param list<string> $availableLocales Locales from bundle config (nowo_dashboard_menu.locales) for label fields per locale
     */
    public function __construct(
        private readonly MenuItemRepository $menuItemRepository,
        private readonly string $defaultLocale = 'en',
        private readonly array $availableLocales = [],
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, array{label: string, params: list<string>}> $appRoutes */
        $appRoutes    = $options['app_routes'];
        $routeChoices = $this->buildRouteChoices($appRoutes);
        /** @var list<string> $availableLocales */
        $availableLocales = $options['available_locales'];
        $t                = fn (string $id): string => $this->translator instanceof TranslatorInterface ? $this->translator->trans($id, [], NowoDashboardMenuBundle::TRANSLATION_DOMAIN) : $id;

        $builder
            ->add('label', TextType::class, [
                'required' => true,
                'label'    => 'form.menu_item_type.label.label',
                'attr'     => ['class' => 'form-control'],
            ])
            ->add('itemType', ChoiceType::class, [
                'choices' => [
                    'form.menu_item_type.type.link'    => MenuItem::ITEM_TYPE_LINK,
                    'form.menu_item_type.type.section' => MenuItem::ITEM_TYPE_SECTION,
                    'form.menu_item_type.type.divider' => MenuItem::ITEM_TYPE_DIVIDER,
                ],
                'label' => 'form.menu_item_type.type.label',
                'attr'  => ['class' => 'form-select'],
                'autocomplete' => true,
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                'label'    => 'form.menu_item_type.position.label',
                'attr'     => ['min' => 0, 'class' => 'form-control'],
            ])
            ->add('linkType', ChoiceType::class, [
                'choices' => [
                    'form.menu_item_type.link_type.route'        => MenuItem::LINK_TYPE_ROUTE,
                    'form.menu_item_type.link_type.external_url' => MenuItem::LINK_TYPE_EXTERNAL,
                ],
                'label' => 'form.menu_item_type.link_type.label',
                'attr'  => ['class' => 'form-select'],
                'autocomplete' => true,
            ])
            ->add('routeName', ChoiceType::class, [
                'required'    => false,
                'label'       => 'form.menu_item_type.route_name.label',
                'placeholder' => $t('form.menu_item_type.route_name.placeholder'),
                'choices'     => $routeChoices,
                'attr'        => ['class' => 'form-select'],
                'choice_attr' => static function ($choice, $key, $value) use ($appRoutes): array {
                    $params = $appRoutes[$value]['params'] ?? [];

                    return ['data-params' => json_encode($params)];
                },
                'autocomplete' => true,
            ])
            ->add('routeParams', TextType::class, [
                'required' => false,
                'label'    => 'form.menu_item_type.route_params.label',
                'attr'     => ['class' => 'form-control font-monospace', 'placeholder' => $t('form.menu_item_type.route_params.placeholder')],
            ])
            ->add('externalUrl', UrlType::class, [
                'required' => false,
                'label'    => 'form.menu_item_type.external_url.label',
                'attr'     => ['class' => 'form-control', 'placeholder' => $t('form.menu_item_type.external_url.placeholder')],
            ])
            ->add('targetBlank', CheckboxType::class, [
                'required'   => false,
                'label'      => 'form.menu_item_type.target_blank.label',
                'attr'       => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
            ])
            ->add('permissionKey', TextType::class, [
                'required' => false,
                'label'    => 'form.menu_item_type.permission_key.label',
                'attr'     => ['class' => 'form-control'],
            ]);

        if (class_exists('Nowo\IconSelectorBundle\Form\IconSelectorType')) {
            $builder->add('icon', IconSelectorType::class, [
                'required' => false,
                'mode'     => IconSelectorType::MODE_TOM_SELECT,
                'label'    => 'form.menu_item_type.icon.label',
                'attr'     => [/* 'class' => 'form-control', */ 'placeholder' => $t('form.menu_item_type.icon.placeholder')],
            ]);
        } else {
            $builder->add('icon', TextType::class, [
                'required' => false,
                'label'    => 'form.menu_item_type.icon.label',
                'attr'     => ['class' => 'form-control', 'placeholder' => $t('form.menu_item_type.icon.placeholder')],
            ]);
        }

        if ($availableLocales !== []) {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($availableLocales): void {
                $data = $event->getData();
                if (!$data instanceof MenuItem) {
                    return;
                }
                $translations = $data->getTranslations() ?? [];
                $form         = $event->getForm();
                foreach ($availableLocales as $locale) {
                    $fieldName = 'label_' . $locale;
                    $form->add($fieldName, TextType::class, [
                        'required'                     => false,
                        'mapped'                       => false,
                        'label'                        => 'form.menu_item_type.label_locale',
                        'label_translation_parameters' => ['%locale%' => $locale],
                        'data'                         => $translations[$locale] ?? null,
                        'attr'                         => ['class' => 'form-control'],
                    ]);
                }
            });

            $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event) use ($availableLocales): void {
                $data = $event->getData();
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

        $builder->get('routeParams')->addModelTransformer(new JsonToArrayTransformer());

        $menu = $options['menu'];
        if ($menu instanceof Menu) {
            $locale     = $options['locale'];
            $excludeIds = $options['exclude_ids'];
            $qb         = $this->menuItemRepository->getPossibleParentsQueryBuilder($menu, $excludeIds);
            $builder->add('parent', EntityType::class, [
                'class'         => MenuItem::class,
                'query_builder' => $qb,
                'choice_label'  => static function (MenuItem $item) use ($locale): string {
                    $parts = [];
                    $p     = $item;
                    while ($p instanceof MenuItem) {
                        array_unshift($parts, $p->getLabelForLocale($locale));
                        $p = $p->getParent();
                    }

                    return implode(' > ', $parts);
                },
                'placeholder'  => $t('form.menu_item_type.parent.placeholder'),
                'required'     => false,
                'label'        => 'form.menu_item_type.parent.label',
                'attr'         => ['class' => 'form-select'],
                'autocomplete' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => MenuItem::class,
            'app_routes'         => [],
            'menu'               => null,
            'exclude_ids'        => [],
            'locale'             => $this->defaultLocale,
            'available_locales'  => $this->availableLocales,
            'method'             => 'POST',
            'translation_domain' => NowoDashboardMenuBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setDefined(['action']);
        $resolver->setAllowedTypes('app_routes', 'array');
        $resolver->setAllowedTypes('menu', [Menu::class, 'null']);
        $resolver->setAllowedTypes('exclude_ids', 'array');
        $resolver->setAllowedTypes('locale', 'string');
        $resolver->setAllowedTypes('available_locales', 'array');
    }

    /**
     * @param array<string, array{label: string, params: list<string>}> $appRoutes
     *
     * @return array<string, string> label => route name
     */
    private function buildRouteChoices(array $appRoutes): array
    {
        $routeChoices = [];
        foreach ($appRoutes as $name => $data) {
            $routeChoices[$data['label']] = $name;
        }

        return $routeChoices;
    }
}
