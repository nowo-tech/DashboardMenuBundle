<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\DependencyInjection;

use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * REQ-TEST-003: the legacy shapes of the choice lists and the anchored-regex guard of area_locale_pattern.
 */
final class ConfigurationNormalizationTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, list<string>}>
     */
    public static function choiceLists(): iterable
    {
        yield 'a plain list keeps the strings' => [['a.service', 7, 'b.service'], ['a.service', 'b.service']];
        yield 'the legacy order key' => [['order' => ['b.service', 3, 'a.service']], ['b.service', 'a.service']];
        yield 'a map keeps its string keys' => [['a.service' => 'Label A', 'b.service' => 'Label B'], ['a.service', 'b.service']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('choiceLists')]
    public function testChoiceListsAreNormalisedToAListOfServiceIds(mixed $input, array $expected): void
    {
        foreach (['menu_link_resolver_choices', 'permission_checker_choices'] as $key) {
            $config = (new Processor())->processConfiguration(new Configuration(), [[$key => $input]]);

            self::assertSame($expected, $config[$key], $key);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function anchoredPatterns(): iterable
    {
        yield 'a character class' => ['/^[a-z]{2}$/D'];
        yield 'a class that starts with a literal bracket' => ['/^[]a]$/D'];
        yield 'a negated class that starts with a literal bracket' => ['/^[^]a]$/D'];
        yield 'an alternation inside a group' => ['/^(es|fr)$/D'];
        yield 'an escaped bar' => ['/^a\|b$/D'];
        yield 'braces as delimiters' => ['{^[a-z]{2}$}D'];
        yield 'an end anchor written \z' => ['/^[a-z]{2}\z/D'];
        yield 'a start anchor written \A' => ['/\A[a-z]{2}$/D'];
        yield 'an extended pattern with a comment line' => ["/^a # comment\n\$/Dx"];
        yield 'an extended pattern whose comment runs to the end' => ['/^a#$/Dx'];
    }

    #[DataProvider('anchoredPatterns')]
    public function testAnAnchoredLocalePatternIsAccepted(string $pattern): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['area_locale_pattern' => $pattern]]);

        self::assertSame($pattern, $config['area_locale_pattern']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unanchoredPatterns(): iterable
    {
        yield 'it does not compile' => ['/^(a$/D'];
        yield 'without the D modifier' => ['/^[a-z]{2}$/'];
        yield 'not anchored at the start' => ['/[a-z]{2}$/D'];
        yield 'not anchored at the end' => ['/^[a-z]{2}/D'];
        yield 'a literal dollar at the end' => ['/^[a-z]\$/D'];
        yield 'a top-level alternation' => ['/^es|fr$/D'];
        yield 'an alternation after a class and a group' => ['/^[|](a)|b$/D'];
        yield 'an alternation after an extended comment' => ["/^a # (comment\n|b\$/Dx"];
    }

    #[DataProvider('unanchoredPatterns')]
    public function testAnUnanchoredLocalePatternIsRefused(string $pattern): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [['area_locale_pattern' => $pattern]]);
    }
}
