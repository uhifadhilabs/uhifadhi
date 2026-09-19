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

namespace Uhifadhi\Bundle\AtlasBundle\Shell;

use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\ShellBundle\Contract\StylesheetSourceInterface;

/**
 * THE TWO ATLAS COMPONENTS THAT ARE DRAWN INSIDE SOMEBODY ELSE'S PAGE,
 * and therefore cannot wait to be linked by it.
 *
 * `atlas_calendar()` is written onto a module's own Calendar tab and
 * `atlas_chart()` onto a topic's record — pages in packages that have
 * never heard of this one. They link no atlas sheet, and the component
 * cannot link one for itself, so the month rendered as a list of days
 * and nobody's test could see it: the markup was right, the page
 * returned 200, and the rules were in a sheet only map pages read.
 *
 * THE MAP IS NOT HERE, ON PURPOSE. A page that draws a map knows it
 * draws one — it asked for a plate, gave it a height and a subject —
 * so it links {@see AtlasBundle::STYLESHEET} for itself, and a page with
 * no map does not pay for Leaflet's chrome.
 */
final readonly class AtlasStylesheets implements StylesheetSourceInterface
{
    /** @return list<string> */
    public function stylesheets(): array
    {
        return [AtlasBundle::CHART_STYLESHEET, AtlasBundle::CALENDAR_STYLESHEET];
    }
}
