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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AtlasBundle\Exception\LayerException;
use Uhifadhi\Bundle\AtlasBundle\Model\FeaturePopup;

/**
 * A POPUP IS PROPERTY NAMES, NEVER MARKUP.
 *
 * What a module states is which of a feature's own properties the popup reads;
 * the plate writes the markup and escapes the values, so a property carrying a
 * stray angle bracket cannot become an element on somebody's map.
 */
final class FeaturePopupTest extends TestCase
{
    public function testAPopupNamesTheHeadlinePropertyAndNothingElse(): void
    {
        self::assertSame(
            ['title' => 'title', 'lines' => [], 'href' => null, 'linkLabel' => null],
            new FeaturePopup('title')->toArray(),
        );
    }

    public function testAPopupCarriesItsLinesAndItsLink(): void
    {
        $popup = new FeaturePopup(
            title: 'title',
            lines: ['category', 'statusLabel'],
            href: 'href',
            linkLabel: 'Open the case file →',
        );

        self::assertSame([
            'title' => 'title',
            'lines' => ['category', 'statusLabel'],
            'href' => 'href',
            'linkLabel' => 'Open the case file →',
        ], $popup->toArray());
    }

    /** The short way to say the two properties a popup nearly always has. */
    public function testTheShorthandNamesTheHeadlineAndTheLink(): void
    {
        self::assertSame(
            ['title' => 'title', 'lines' => [], 'href' => 'href', 'linkLabel' => null],
            FeaturePopup::of('title', 'href')->toArray(),
        );
    }

    /** A link label with no property behind it would print a link to nowhere. */
    public function testALinkLabelWithoutAHrefPropertyIsRefused(): void
    {
        $this->expectException(LayerException::class);

        new FeaturePopup(title: 'title', linkLabel: 'Open the case file →');
    }

    public function testAPopupWithNoTitlePropertyIsRefused(): void
    {
        $this->expectException(LayerException::class);

        new FeaturePopup('');
    }
}
