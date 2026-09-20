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
 * WHAT A POSITION GRANTS IS ONE COLUMN — EVERY PERMISSION A FULL-WIDTH ROW.
 *
 * The ledger on the person's record used to fill TWO columns inside the card
 * (`grid-auto-flow: column` over six row tracks). Beside the record's right
 * rail those halves read as a third and a fourth column, and each permission
 * was left with about 360px for a name, a key and a reason — so the key
 * wrapped and the reason was cut. The owner's verdict: "3 columns ... is too
 * much and clamps the permission info".
 *
 * WHY A TEST AND NOT JUST A DELETION. Every clamp here was written as a
 * `.mb-eff2` override of the base row — a narrower reason track, a nowrap
 * name, a column gap — and each one is invisible in a diff of the page: the
 * ledger still renders, just squeezed. The rule this test holds is the whole
 * ruling in one line: `.mb-eff2` may say where the ledger starts and nothing
 * about how wide its rows are, at ANY width. The base `.pm-eff` /
 * `.pm-effrow` — the same rows the permissions pages draw — do the drawing.
 *
 * @see /Users/eemjema/Programming/DesignsProjects/team-module/team-pages.css (commit 7f8d52a)
 */
#[CoversNothing]
final class GrantsAreOneColumnTest extends TestCase
{
    /**
     * The ledger's own rule places it and stops. `margin-top` is the gap
     * under the label; anything laying out columns is the clamp coming back.
     */
    public function testTheLedgerOnlyStatesItsGap(): void
    {
        self::assertSame(['margin-top'], self::propertiesOf('.mb-eff2'));
    }

    /**
     * No `.mb-eff2` rule may reshape the base row — that is where every
     * clamped track and every nowrap name lived.
     */
    public function testNoRuleReshapesTheRowsUnderTheLedger(): void
    {
        foreach (self::selectors() as $selector) {
            self::assertFalse(
                str_contains($selector, '.mb-eff2') && '.mb-eff2' !== $selector,
                \sprintf('`%s` reshapes the ledger\'s rows; a permission is one full-width row, drawn by the base rule.', $selector),
            );
        }
    }

    /**
     * AT EVERY WIDTH. The old flow had a width-conditional twin that swapped
     * the two columns back to one below 1160px; one column at every width
     * means no `@media` may mention the ledger at all.
     */
    public function testNoWidthConditionalRuleMentionsTheLedger(): void
    {
        foreach (self::mediaBlocks() as $condition => $body) {
            self::assertStringNotContainsString(
                '.mb-eff2',
                $body,
                \sprintf('`@media %s` still tunes the ledger; the one-column ledger is width-unconditional.', $condition),
            );
        }
    }

    /**
     * The rows themselves are the ones the permissions pages draw, and the
     * base rule is what draws them: a two-track row, one full card wide.
     */
    #[DataProvider('theBaseRowsDeclarations')]
    public function testTheBaseRowStillDrawsAFullWidthRow(string $property, string $value): void
    {
        self::assertMatchesRegularExpression(
            '/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*'.preg_quote($value, '/').'\s*(?:;|$)/',
            self::rule('.pm-effrow'),
            \sprintf('The base permission row must state `%s: %s`.', $property, $value),
        );
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function theBaseRowsDeclarations(): \Generator
    {
        yield 'display' => ['display', 'grid'];
        yield 'grid-template-columns' => ['grid-template-columns', '1fr 168px'];
        yield 'gap' => ['gap', '0 16px'];
    }

    /**
     * Property names declared by one selector's rules, in order.
     *
     * @return list<string>
     */
    private static function propertiesOf(string $selector): array
    {
        $properties = [];
        foreach (explode(';', self::rule($selector)) as $declaration) {
            if ('' !== trim($declaration)) {
                $properties[] = trim(explode(':', $declaration, 2)[0]);
            }
        }

        return $properties;
    }

    /** One declaration block, by its selector. */
    private static function rule(string $selector): string
    {
        preg_match_all('/([^{}@]*)\{([^{}]*)\}/s', self::css(), $matches, \PREG_SET_ORDER);

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

    /**
     * Every selector written in the sheet, one per comma-separated part.
     *
     * @return list<string>
     */
    private static function selectors(): array
    {
        preg_match_all('/([^{}@]*)\{[^{}]*\}/s', self::css(), $matches, \PREG_SET_ORDER);

        $selectors = [];
        foreach ($matches as $match) {
            foreach (explode(',', $match[1]) as $written) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $written));
                if ('' !== $selector) {
                    $selectors[] = $selector;
                }
            }
        }

        return $selectors;
    }

    /**
     * Every `@media` block's condition and body.
     *
     * @return array<string, string>
     */
    private static function mediaBlocks(): array
    {
        $css = self::css();
        $blocks = [];

        $offset = 0;
        while (1 === preg_match('/@media([^{]*)\{/', $css, $found, \PREG_OFFSET_CAPTURE, $offset)) {
            $start = (int) $found[0][1] + \strlen($found[0][0]);
            $depth = 1;
            $index = $start;
            while ($index < \strlen($css) && $depth > 0) {
                $depth += match ($css[$index]) {
                    '{' => 1,
                    '}' => -1,
                    default => 0,
                };
                ++$index;
            }

            $blocks[trim((string) $found[1][0])] = substr($css, $start, $index - $start - 1);
            $offset = $index;
        }

        self::assertNotSame([], $blocks, 'No @media block in team.css at all — the reader is broken, not the sheet.');

        return $blocks;
    }

    /** The sheet, with its comments stripped. */
    private static function css(): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(\dirname(__DIR__, 3).'/public/team.css'));
    }
}
