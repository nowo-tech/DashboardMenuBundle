<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

use function is_array;
use function is_string;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Transforms JSON string (form) to array|null (entity) and back.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class JsonToArrayTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string
    {
        if ($value === null || $value === []) {
            return '{}';
        }
        if (!is_array($value)) {
            return '{}';
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function reverseTransform(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '{}') {
            return null;
        }
        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            throw new TransformationFailedException('Invalid JSON for route params.');
        }

        return $decoded;
    }
}
