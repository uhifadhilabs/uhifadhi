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

namespace Uhifadhi\Contracts\Tests\Atlas;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Atlas\PlatePalette;

/**
 * A CATEGORY IS A POSITION, AND EIGHTEEN OF THEM ARE EIGHTEEN MARKS.
 *
 * RULED 2026-09-21: past nine the set continues as a second lightness ring of
 * the same nine hues, never a tenth hue. What this side has to guarantee is
 * the part a caller can rely on — every position in range resolves to its own
 * token, and a position out of range is refused rather than quietly wrapped
 * into somebody else's colour.
 */
final class PlatePaletteTest extends TestCase
{
    public function testEveryPositionResolvesToItsOwnPlateToken(): void
    {
        $tokens = [];
        for ($position = 1; $position <= PlatePalette::CATEGORIES; ++$position) {
            $tokens[] = PlatePalette::category($position);
        }

        self::assertCount(PlatePalette::CATEGORIES, array_unique($tokens));
        self::assertSame('var(--cat-p-1)', $tokens[0]);
        self::assertSame('var(--cat-p-10)', $tokens[9], 'the first member of the ring');
        self::assertSame('var(--cat-p-18)', $tokens[17]);
    }

    /** The ring is part of the set, so every one of them is a token like any other. */
    public function testTheRingsTokensPassTheSameDoorAsTheNine(): void
    {
        for ($position = 1; $position <= PlatePalette::CATEGORIES; ++$position) {
            self::assertTrue(PlatePalette::isToken(PlatePalette::category($position)));
        }
    }

    /**
     * AND WRAPPING IS THE CALLER'S, still. The palette refuses a nineteenth
     * rather than inventing one, because what a set does when it runs out is
     * the set's business and a silent modulo here would hide it.
     */
    public function testAPositionPastTheRingIsRefusedRatherThanWrapped(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('1 to 18');

        PlatePalette::category(PlatePalette::CATEGORIES + 1);
    }

    public function testAPositionBelowOneIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PlatePalette::category(0);
    }
}
