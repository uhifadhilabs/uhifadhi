<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AreaBundle\Overview\OverviewVocabulary;

/**
 * WHAT THE OVERVIEW PROMISES A CONTRIBUTED CELL, AND THE KEEPING OF IT.
 *
 * The list is a promise a module writes its markup against; the area's
 * stylesheet is where the promise is kept. The failure this catches is the
 * one that was measured twice: a contributed cell drawing a flow bar whose
 * rules no linked sheet defines, so its segments render as blue underlined
 * links on a page the module does not own and cannot fix.
 *
 * AND THE DOCUMENTATION PRINTS THE SAME LIST, because a published
 * vocabulary that disagrees with the page it is documented on is worse than
 * none: a module author would write the documented name.
 */
#[CoversClass(OverviewVocabulary::class)]
final class OverviewVocabularyTest extends TestCase
{
    /** @return list<array{string}> */
    public static function classes(): array
    {
        return array_map(static fn (string $class): array => [$class], OverviewVocabulary::HOST_CLASSES);
    }

    #[DataProvider('classes')]
    public function testEveryPromisedClassIsDefinedInThisSurfacesSheet(string $class): void
    {
        self::assertMatchesRegularExpression(
            '/\.'.preg_quote($class, '/').'\b[^{]*\{/',
            self::sheet(),
            \sprintf('The overview promises a module `.%s` and no rule in area.css defines it.', $class),
        );
    }

    /**
     * A FIGURE CARD'S QUALIFIER IS ONE LINE ON THIS STRIP.
     *
     * The shell lays a qualifier out as a flex row, which is right where it
     * is a run and a chip and wrong where it is a sentence with one word
     * coloured: a `<span class="r">` inside a flex row is a flex ITEM, so
     * "23 urgent" sat on a line of its own and the caption read as two. The
     * design states the override on this strip, and so does this surface —
     * widening the shell's rule would change every figure card in the
     * product to fix one.
     */
    public function testTheRightNowStripsQualifierIsOneLine(): void
    {
        self::assertMatchesRegularExpression(
            '/\.ao-kstrip\s+\.kpi\s+\.sub\s*\{[^}]*display:\s*block/',
            self::sheet(),
            'A coloured run inside the caption must stay in the caption line.',
        );
    }

    /** The documented list is the published list, name for name. */
    public function testTheDocumentationPrintsExactlyThisList(): void
    {
        $documented = [];
        preg_match('/<!-- overview-vocabulary -->(.*?)<!-- \/overview-vocabulary -->/s', self::doc(), $block);
        self::assertSame(2, \count($block), 'the module guide carries the marked vocabulary list');

        // THE ENTRY CLASS IS THE FIRST CELL OF ITS ROW. A row's prose names
        // the entry's parts as well (`.n`, `a.s1`), and a part without its
        // entry is not a thing a module can write.
        preg_match_all('/^\|\s*`\.([a-z0-9-]+)`\s*\|/m', $block[1] ?? '', $found);
        foreach ($found[1] as $class) {
            $documented[] = $class;
        }

        self::assertSame(OverviewVocabulary::HOST_CLASSES, $documented);
    }

    private static function sheet(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/public/area.css');
    }

    private static function doc(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 5).'/Contracts/docs/module-development.md');
    }
}
