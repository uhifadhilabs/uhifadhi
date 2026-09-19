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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Template;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * THE FOCUSED DEPARTMENT WEARS A LINE, AND THE LINE IS DRAWN BY A RULE.
 *
 * WHY A TEST FOR ONE PSEUDO-ELEMENT. The focused card is the one the
 * sidebar's lit entry points at, and it is marked apart from the OPEN one:
 * open is told by a body and a turned chevron, focus by a 2px accent line
 * down the left border. Everything else about the treatment — the border
 * tint, the lifted bands — renders even when the line does not, so a missing
 * line is invisible in a diff and nearly invisible on the page: the card
 * still looks different, just not the ruled way. It went missing once.
 *
 * INSET BY THE RADIUS AT BOTH ENDS, so the line starts where the top corner
 * ends and stops where the bottom corner begins; laid at -1px it sits ON the
 * border rather than inside the card, which is why the register's card has to
 * let it out (`overflow: visible`) — the base card clips.
 *
 * The values are the design's, read value for value.
 *
 * @see /Users/eemjema/Programming/DesignsProjects/uhifadhi-web/departments/overview-designs.css lines 843-852
 */
#[CoversNothing]
final class FocusedCardTest extends TestCase
{
    #[DataProvider('theLinesDeclarations')]
    public function testTheFocusedCardsAccentLineIsDrawn(string $property, string $value): void
    {
        self::assertMatchesRegularExpression(
            '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*'.preg_quote($value, '/').'\s*(?:;|$)/',
            self::rule('.dcard.dcfocus::after'),
            \sprintf('The focused card\'s line must state `%s: %s`.', $property, $value),
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function theLinesDeclarations(): \Generator
    {
        // content FIRST: without it the pseudo-element generates no box at
        // all, and every other value below is describing nothing.
        yield 'content' => ['content', '""'];
        yield 'position' => ['position', 'absolute'];
        yield 'left' => ['left', '-1px'];
        yield 'top' => ['top', '13px'];
        yield 'bottom' => ['bottom', '13px'];
        yield 'width' => ['width', '2px'];
        yield 'border-radius' => ['border-radius', '2px'];
        yield 'background' => ['background', 'rgb(var(--c-acc))'];
    }

    /**
     * THE CARD HAS TO BE THE LINE'S CONTAINING BLOCK, and it has to let the
     * line out: the base department card clips its overflow, and a line laid
     * on the border of a clipping box is a line nobody sees.
     */
    public function testTheRegistersCardPositionsTheLineAndDoesNotClipIt(): void
    {
        $card = self::rule('.dcstack.dcreg .dcard');

        self::assertMatchesRegularExpression('/position:\s*relative/', $card);
        self::assertMatchesRegularExpression('/overflow:\s*visible/', $card);
    }

    /** One declaration block, by its selector. */
    private static function rule(string $selector): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(\dirname(__DIR__, 3).'/public/team.css'));

        preg_match_all('/([^{}@]*)\{([^{}]*)\}/s', $css, $matches, \PREG_SET_ORDER);

        $declarations = [];
        foreach ($matches as $match) {
            foreach (explode(',', $match[1]) as $written) {
                if (1 === preg_match('/^\s*'.preg_quote($selector, '/').'\s*$/', (string) preg_replace('/\s+/', ' ', $written))) {
                    $declarations[] = trim($match[2]);
                }
            }
        }

        self::assertNotSame([], $declarations, \sprintf('No `%s` rule in team.css at all.', $selector));

        return implode(';', $declarations);
    }
}
