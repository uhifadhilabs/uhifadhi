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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\TeamBundle\Model\SectionBar;

/**
 * A RANKED BAR'S TWO WIDTHS — worked out here, and not in a template.
 *
 * A percentage computed in Twig is a percentage nothing can test, and the one
 * reading this card exists to give — "which department is the big one" — is
 * exactly the reading a per-row scale destroys.
 */
final class SectionBarTest extends TestCase
{
    /**
     * SCALED TO THE LARGEST ROW, NOT TO ITS OWN TOTAL. Nine of eleven against a
     * largest of thirty-five is a quarter of the width, not four fifths — that
     * is the whole point of the card.
     */
    public function testTheWidthsAreTakenAgainstTheLargestRow(): void
    {
        $bar = (new SectionBar('Ecology', value: 9, total: 11, people: 9, note: ''))->scaledTo(35);

        self::assertSame(25.7, $bar->filledWidth);
        self::assertSame(5.7, $bar->restWidth);
    }

    /** The largest row fills its track exactly, and nothing overflows it. */
    public function testTheLargestRowFillsTheTrack(): void
    {
        $bar = (new SectionBar('Protection Service', value: 31, total: 35, people: 31, note: ''))->scaledTo(35);

        self::assertSame(100.0, $bar->filledWidth + $bar->restWidth);
    }

    /**
     * A ROW WITH NOTHING IN IT IS QUIET, and keeps its row. A department with
     * no position is an answer — "nothing is filed here yet" — and dropping it
     * from the card would hide the very thing a reader came to see.
     */
    public function testARowWithNoTotalIsQuietAndDrawsNoBar(): void
    {
        $bar = (new SectionBar('Veterinary Services', value: 0, total: 0, people: 0, note: 'no positions'))->scaledTo(35);

        self::assertTrue($bar->isQuiet());
        self::assertSame(0.0, $bar->filledWidth);
        self::assertSame(0.0, $bar->restWidth);
    }

    /**
     * AND AN INSTALLATION WHERE EVERY ROW IS EMPTY DIVIDES BY NOTHING. The
     * first day of an installation is a real state, not an edge case.
     */
    public function testAnEmptyInstallationDividesByNothing(): void
    {
        $bar = (new SectionBar('Ecology', value: 0, total: 0, people: 0, note: ''))->scaledTo(0);

        self::assertSame(0.0, $bar->filledWidth);
    }
}
