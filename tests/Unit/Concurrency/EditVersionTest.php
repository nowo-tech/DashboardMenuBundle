<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Concurrency;

use Nowo\DashboardMenuBundle\Concurrency\EditVersion;
use Nowo\DashboardMenuBundle\Concurrency\StaleEditException;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.24 (REQ-RUNTIME-003 rule 4): the optimistic edit version of the dashboard forms and the new-item submit token.
 */
final class EditVersionTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, ?int}>
     */
    public static function postedVersions(): iterable
    {
        yield 'string' => ['3', 3];
        yield 'zero' => ['0', 0];
        yield 'int' => [4, 4];
        yield 'negative int' => [-1, null];
        yield 'empty' => ['', null];
        yield 'null' => [null, null];
        yield 'not a number' => ['abc', null];
        yield 'signed' => ['-2', null];
        yield 'too long' => ['1234567890', null];
        yield 'array' => [['1'], null];
    }

    #[DataProvider('postedVersions')]
    public function testPostedReadsOnlyANonNegativeInteger(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, EditVersion::posted($value));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function submitTokens(): iterable
    {
        yield 'hex' => ['formtoken0000000000000000000001', 'formtoken0000000000000000000001'];
        yield 'longest' => [str_repeat('a', 64), str_repeat('a', 64)];
        yield 'too short' => ['abc', ''];
        yield 'too long' => [str_repeat('a', 65), ''];
        yield 'punctuation' => ['a1b2c3d4e5f6-718293a4b5c6d7e8f90', ''];
        yield 'null' => [null, ''];
        yield 'int' => [1234567890123456, ''];
    }

    #[DataProvider('submitTokens')]
    public function testSubmitTokenIsAnAlphanumericStringOr(mixed $value, string $expected): void
    {
        self::assertSame($expected, EditVersion::submitToken($value));
    }

    public function testTheStoredVersionMustBeThePostedOne(): void
    {
        EditVersion::assertCurrent(3, 3);
        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{?int, ?int}>
     */
    public static function staleCases(): iterable
    {
        yield 'another admin saved' => [4, 3];
        yield 'the row is gone' => [null, 3];
        yield 'no version posted' => [3, null];
        yield 'nothing at all' => [null, null];
    }

    #[DataProvider('staleCases')]
    public function testAnyOtherCaseIsStale(?int $stored, ?int $posted): void
    {
        $this->expectException(StaleEditException::class);
        $this->expectExceptionMessage(StaleEditException::MESSAGE);

        EditVersion::assertCurrent($stored, $posted);
    }

    public function testBumpingCountsUpFromOne(): void
    {
        $menu = new Menu();
        $item = new MenuItem();
        self::assertSame(1, $menu->getEditVersion());
        self::assertSame(1, $item->getEditVersion());

        self::assertSame($menu, $menu->bumpEditVersion());
        self::assertSame($item, $item->bumpEditVersion());
        self::assertSame(2, $menu->getEditVersion());
        self::assertSame(2, $item->getEditVersion());
    }

    public function testAnItemKeepsItsSubmitToken(): void
    {
        $item = new MenuItem();
        self::assertNull($item->getSubmitToken());

        $item->setSubmitToken('formtoken0000000000000000000001');
        self::assertSame('formtoken0000000000000000000001', $item->getSubmitToken());
    }
}
