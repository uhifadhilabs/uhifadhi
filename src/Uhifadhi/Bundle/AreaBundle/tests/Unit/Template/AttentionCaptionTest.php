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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * WHAT THE ATTENTION COUNT IS MADE OF, in one line.
 *
 * THE SHAPE IS `<urgent> · <per module…>` and every piece of it is a real
 * count. Rendered rather than asserted through the page, because the defect
 * this exists for is a punctuation one — the caption opened with a separator
 * when the urgent count was nought — and that only shows in the string.
 */
#[CoversNothing]
final class AttentionCaptionTest extends TestCase
{
    public function testTheCaptionLeadsWithTheUrgentCountAndThenEachModule(): void
    {
        $caption = self::render(3, ['Patrols' => 11, 'incidents' => 24], 38);

        self::assertSame('<span class="r">3 urgent</span> &middot; 11 patrols &middot; 24 incidents', $caption);
    }

    /**
     * NOUGHT URGENT IS NOT A PIECE. The caption starts at the first module
     * count, with no separator in front of it — and "0 urgent" is never
     * printed, because nobody writes that.
     */
    public function testNothingUrgentMeansNoLeadingSeparatorAndNoNought(): void
    {
        $caption = self::render(0, ['Patrols' => 11, 'incidents' => 24], 35);

        self::assertSame('11 patrols &middot; 24 incidents', $caption);
        self::assertStringNotContainsString('0 urgent', $caption);
    }

    /** A quiet morning says so, whatever the breakdown would have been. */
    public function testAnEmptyListIsAGoodDay(): void
    {
        self::assertSame('a good day', self::render(0, [], 0));
    }

    /** @param array<string, int> $byModule */
    private static function render(int $urgent, array $byModule, int $total): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 3).'/templates'));

        $html = $twig->render('area/overview/_w_nowbar.html.twig', [
            'nowTiles' => [],
            'attention' => array_fill(0, $total, 'an item'),
            'attentionSummary' => ['urgent' => $urgent, 'byModule' => $byModule],
        ]);

        preg_match('#<span class="sub">(.*?)</span>\s*</div>#s', $html, $found);
        self::assertSame(2, \count($found), 'the attention tile renders a caption');

        return trim($found[1] ?? '');
    }
}
