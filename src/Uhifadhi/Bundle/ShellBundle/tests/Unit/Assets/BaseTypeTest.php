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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Assets;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * THE TYPE EVERY PAGE INHERITS IS THE SHELL'S, AND IT IS STATED ONCE.
 *
 * WHY A TEST AT ALL. Two defects of exactly this kind were measured on
 * rendered pages before anybody suspected the stylesheet: an identity band
 * six pixels short on every area screen, because the base line-height was the
 * browser's rather than the design's; and a card label a point small on every
 * area screen, because a second sheet restated one of the shell's own
 * selectors with different numbers and, being linked later, won. Neither is
 * visible in a diff of the page that shows it — both are one line in a sheet
 * nobody was looking at.
 *
 * SO THE RULE IS: the base type lives on `body` in the shell, and a bundle's
 * own sheet may add vocabulary the shell does not have but may not restate a
 * selector the shell already ships. An override that is genuinely wanted
 * changes the shell, where every surface gets it.
 */
#[CoversNothing]
final class BaseTypeTest extends TestCase
{
    /** The sheets that are allowed to exist beside the shell's, and must not fight it. */
    private const array LAYERED = ['area', 'team', 'atlas'];

    public function testTheShellStatesTheBaseSizeAndLineHeightEveryPageInherits(): void
    {
        $body = self::ruleFor(self::shell(), 'body');

        self::assertMatchesRegularExpression('/font-size:\s*14px/', $body, 'The design sets the base size at 14px.');
        self::assertMatchesRegularExpression('/line-height:\s*1\.5/', $body, 'The design sets the base line at 1.5.');
    }

    /**
     * EVERY FIGURE CARD IS THE SAME HEIGHT, whatever its content.
     *
     * A ROW OF CARDS IS READ ACROSS, and a card that shrank because its
     * qualifier was one line shorter would make the row look like a chart of
     * something. The design fixes the plate's height and lets the content sit
     * inside it, so a card saying "no module publishes this" is exactly as
     * tall as one saying "88 · from 10 stations".
     */
    public function testEveryFigureCardIsTheSameHeightWhateverItSays(): void
    {
        self::assertMatchesRegularExpression(
            '/height:\s*107px/',
            self::ruleFor(self::shell(), '.c.kpi, .kpi'),
            'A figure card whose height follows its content makes a row of them read as a chart.',
        );
    }

    /**
     * NO LAYERED SHEET RESTATES THE CARD LABEL. It is the shell's, at the
     * design's 9.5px, and a second copy of it is how one screen ends up
     * wearing a different size from the rest of the product.
     */
    public function testNoLayeredSheetRestatesTheCardsOwnLabel(): void
    {
        foreach (self::LAYERED as $sheet) {
            $css = self::sheet($sheet);
            if (null === $css) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression(
                '/^\s*\.c\s*>?\s*\.tab\s*\{/m',
                $css,
                \sprintf('%s.css restates the shell\'s `.c > .tab`; change the shell instead.', $sheet),
            );
        }
    }

    private static function shell(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/public/shell.css');
    }

    private static function sheet(string $name): ?string
    {
        $path = \dirname(__DIR__, 5).'/Bundle/'.ucfirst($name).'Bundle/public/'.$name.'.css';

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /** One declaration block, by its selector. */
    private static function ruleFor(string $css, string $selector): string
    {
        preg_match('/^'.preg_quote($selector, '/').'\s*\{([^}]*)\}/m', $css, $found);
        $block = $found[1] ?? '';

        self::assertNotSame('', $block, \sprintf('No `%s` rule in the sheet at all.', $selector));

        return $block;
    }
}
