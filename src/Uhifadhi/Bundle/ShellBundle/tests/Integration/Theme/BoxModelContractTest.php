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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Theme;

use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ContractTestCase;

/**
 * SPEC 7 — THE BOX MODEL.
 *
 * The frame guarantees border-box. Every element on an uhifadhi page measures
 * its padding and its border INSIDE the width it was given, and the guarantee
 * is the shell's because the shell is the only sheet every page loads.
 *
 * WHY THIS IS A CONTRACT AND NOT A PREFERENCE. The design workspace's replicas
 * open with `*{box-sizing:border-box}`, so every number in every design — a
 * 78px calendar cell, a 236px rail, a 226px roster column — is authored as a
 * BORDER box. A module that ports one of those screens faithfully, into a
 * platform whose frame shipped no reset, gets a box that is correct in the
 * replica and too big in the product, by exactly its padding and border. That
 * is not a bug a module can be careful about: it is a bug the fleet inherits
 * once, per rule, silently, and it has already been paid for twice — the team
 * rail that hung 24px out of its own column, and a sign-in card sitting on 52px
 * of phantom scroll.
 *
 * SO IT MOVES INTO THE FRAME. Not into every module's sheet: a rule nine sheets
 * restate is a rule with nine chances to be forgotten, which is the same
 * argument that brought the component vocabulary here in the previous ring.
 *
 * WHAT A MODULE MAY NOW STOP WRITING. `box-sizing: border-box` on its own
 * rules. Keeping it is harmless and, where a module wants the guarantee spelled
 * out at the point of use, honest — but it is no longer load-bearing.
 */
final class BoxModelContractTest extends ContractTestCase
{
    /**
     * THE RESET, IN THE MODERN FORM. The pseudo-elements are named because they
     * are not covered by `*` alone, and a `::before` that measures differently
     * from the element it decorates is the hairline that will not line up.
     */
    public function testTheFrameShipsTheBorderBoxReset(): void
    {
        self::assertMatchesRegularExpression(
            '/\*\s*,\s*\*::before\s*,\s*\*::after\s*\{[^}]*box-sizing:\s*border-box/s',
            $this->stylesheet(),
            <<<'WHY'
                The frame guarantees border-box and the guarantee is gone. Every
                module sheet on the platform is written against it — see
                docs/theming.md.
                WHY,
        );
    }

    /**
     * IT IS PART OF THE PREAMBLE, not a line somewhere in the furniture. A
     * reader looking for what this sheet assumes should find it before the
     * first thing that assumes it, and a module author reading the top of the
     * file should not have to scroll to learn the box model.
     */
    public function testTheResetIsDeclaredBeforeTheFirstRuleThatRelaxesOnIt(): void
    {
        $css = $this->stylesheet();

        $reset = strpos($css, '*::after');
        $firstClassRule = preg_match('/^\s*\.[a-zA-Z]/m', $css, $m, \PREG_OFFSET_CAPTURE) ? $m[0][1] : false;

        self::assertIsInt($reset);
        self::assertIsInt($firstClassRule);
        self::assertLessThan(
            $firstClassRule,
            $reset,
            'The box model is an assumption of everything below it, so it is declared above everything below it.',
        );
    }

    /**
     * AND NOTHING IN THE SHEET TAKES IT BACK. A second wildcard, setting
     * content-box, would leave the platform with a box model that depends on
     * which of two rules a reader found first.
     */
    public function testNothingInTheFrameRestoresTheContentBox(): void
    {
        $declarations = preg_replace('~/\*.*?\*/~s', '', $this->stylesheet());
        self::assertIsString($declarations);

        self::assertStringNotContainsString(
            'content-box',
            $declarations,
            'A rule in the frame restores the content box. The guarantee is only worth anything if it is unconditional.',
        );
    }
}
