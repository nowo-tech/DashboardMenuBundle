<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Entity;

/**
 * Entity with translatable label (e.g. via JSON translations or default label).
 * MenuItem implements this; repository resolves label by locale on load.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface TranslatableInterface
{
    /**
     * Default/current label (after locale resolution in repository, or stored value).
     */
    public function getLabel(): string;

    /**
     * Label for the given locale (e.g. from translations[locale] ?? label).
     */
    public function getLabelForLocale(string $locale): string;
}
