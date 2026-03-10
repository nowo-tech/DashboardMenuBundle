<?php

declare(strict_types=1);

namespace App\Enum;

enum AppLocale: string
{
    case EN = 'en';
    case ES = 'es';
    case FR = 'fr';

    /** Patrón para requirements de rutas (_locale). */
    public const ROUTE_REQUIREMENT = 'en|es|fr';

    /** Locale por defecto. */
    public const DEFAULT = 'en';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
