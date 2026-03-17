<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Form\DataTransformer;

use Nowo\DashboardMenuBundle\Form\DataTransformer\JsonToArrayTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class JsonToArrayTransformerTest extends TestCase
{
    private JsonToArrayTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new JsonToArrayTransformer();
    }

    public function testTransformReturnsEmptyJsonForNull(): void
    {
        self::assertSame('{}', $this->transformer->transform(null));
    }

    public function testTransformReturnsEmptyJsonForEmptyArray(): void
    {
        self::assertSame('{}', $this->transformer->transform([]));
    }

    public function testTransformReturnsEmptyJsonForNonArray(): void
    {
        self::assertSame('{}', $this->transformer->transform('string'));
        self::assertSame('{}', $this->transformer->transform(123));
    }

    public function testTransformEncodesArrayToJson(): void
    {
        $value = ['page' => 'dashboard', 'tab' => 'overview'];
        self::assertSame('{"page":"dashboard","tab":"overview"}', $this->transformer->transform($value));
    }

    public function testReverseTransformReturnsNullForNull(): void
    {
        self::assertNull($this->transformer->reverseTransform(null));
    }

    public function testReverseTransformReturnsNullForEmptyString(): void
    {
        self::assertNull($this->transformer->reverseTransform(''));
    }

    public function testReverseTransformThrowsForNonString(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('Expected a string.');
        $this->transformer->reverseTransform(123);
    }

    public function testReverseTransformReturnsNullForEmptyTrimmedString(): void
    {
        self::assertNull($this->transformer->reverseTransform('   '));
    }

    public function testReverseTransformReturnsNullForEmptyJsonObject(): void
    {
        self::assertNull($this->transformer->reverseTransform('{}'));
        self::assertNull($this->transformer->reverseTransform('  {}  '));
    }

    public function testReverseTransformThrowsForInvalidJson(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('Invalid JSON for route params.');
        $this->transformer->reverseTransform('not json');
    }

    public function testReverseTransformThrowsWhenDecodedIsNotArray(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('Invalid JSON for route params.');
        $this->transformer->reverseTransform('123');
    }

    public function testReverseTransformReturnsDecodedArray(): void
    {
        $json   = '{"page":"dashboard","view":"overview"}';
        $result = $this->transformer->reverseTransform($json);
        self::assertIsArray($result);
        self::assertSame(['page' => 'dashboard', 'view' => 'overview'], $result);
    }
}
