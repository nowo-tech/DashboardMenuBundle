<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\AutoTagPermissionCheckersPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\PermissionCheckerPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Dashboard menu bundle: configurable menus with i18n, tree structure, permissions, Twig and JSON API.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoDashboardMenuBundle extends Bundle
{
    /** Translation domain for bundle strings (dashboard UI, form labels, validation messages). */
    public const TRANSLATION_DOMAIN = 'NowoDashboardMenuBundle';

    /**
     * Tom Select: append the dropdown to document body so it is not clipped by Bootstrap scrollable modals.
     * Merge into {@code tom_select_options} for fields using Symfony UX Autocomplete.
     *
     * @var array<string, mixed>
     */
    public const TOM_SELECT_MODAL_DROPDOWN = [
        'dropdownParent' => 'body',
    ];

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AutoTagPermissionCheckersPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 200);
        $container->addCompilerPass(new TwigPathsPass());
        $container->addCompilerPass(new PermissionCheckerPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new DashboardMenuExtension();
        }

        /** @var ExtensionInterface $extension */
        $extension = $this->extension;

        return $extension;
    }
}
