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

namespace Uhifadhi\Bundle\AreaBundle\Widget;

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Overview\OverviewContributorInterface;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetGroup;

/**
 * THE AREA'S OWN CELLS ON ITS OVERVIEW — and it contributes them the same way
 * a module does.
 *
 * THE PAGE OWNS THE SURFACE AND WRITES NO WIDGET MARKUP. That is the whole
 * point of the seam: if the area bundle rendered its own cards directly and
 * modules went through a contract, the page would have two ways of putting a
 * card on one grid and only one of them would be open to extension. So the
 * area is a contributor with the area's slug, and its cells are read out of
 * the same catalogue in the same order as everybody else's.
 *
 * FOUR OF THEM ARE THE AREA'S BY RIGHT: what this place IS (the band), what
 * is happening this minute (the strip of tiles other modules fill), what
 * needs somebody (the attention list every module files into) and the ground
 * itself. The fifth is the registry's — which modules are switched on here —
 * and the sixth is a SLOT HELD OPEN for the roster's own card.
 *
 * WHY THE HELD SLOT IS DRAWN AT ALL. The design's default composition has a
 * "Stations & who is on" card from the roster module, which this
 * installation has not published yet. Dropping the cell would leave the row
 * half empty and say nothing; drawing the house's absence card in its place
 * says which module is missing and keeps the row the shape the design draws.
 * When roster publishes its widget, its contributor supplies the cell and
 * this one goes.
 */
final class AreaOverviewWidgets implements OverviewContributorInterface
{
    /** The area's own "module" slug — it is always asked. */
    public const string SLUG = 'area';

    public const string IDENT = 'ident';
    public const string NOWBAR = 'nowbar';
    public const string ATTENTION = 'attention';
    public const string MAP = 'map';
    public const string PRESENCE = 'presence';
    public const string MODULES = 'modules';

    public function moduleSlug(): string
    {
        return self::SLUG;
    }

    public function group(): WidgetGroup
    {
        return new WidgetGroup(
            'area',
            'The area',
            'What this place is, what is happening in it right now, what needs somebody, and the ground itself. Contributed by the area, and here whichever modules are switched on.',
        );
    }

    public function widgets(): array
    {
        return [
            new Widget(self::IDENT, 'The area', 'area', 12, [12], true,
                'Its size, how it is divided, what stands on it and where it is — the facts that are true of the place rather than of a day.'),
            new Widget(self::NOWBAR, 'Right now', 'area', 12, [12], true,
                'One tile per module with something happening this minute, in that module’s own words.'),
            new Widget(self::ATTENTION, 'Needs attention', 'area', 12, [12], true,
                'A row per thing a module says somebody has to act on, sorted by urgency across all of them, bounded to the latest few.'),
            new Widget(self::MAP, 'Where', 'area', 12, [12], true,
                'The area’s own plate — its boundary and its zones — with every module’s layer drawn over it.'),
            new Widget(self::PRESENCE, 'Stations & who is on', 'area', 6, [12, 6], true,
                'Who is on the ground right now. The roster module’s card; until it publishes one, the slot says so.'),
            new Widget(self::MODULES, 'Modules in this area', 'area', 6, [12, 6], true,
                'Which modules are switched on here, what each one is reading, and the way into it.'),
        ];
    }

    public function partialPattern(): string
    {
        return '@Area/area/overview/_w_%s.html.twig';
    }

    /**
     * NOTHING, AND THAT IS DELIBERATE. Every fact these cells draw is already
     * resolved by the controller for the page as a whole — the band's
     * measurements, the tiles, the attention list, the plate — and computing
     * them again here would be the same queries twice and two answers that
     * could disagree. A module's contributor has no such caller, which is
     * why the contract offers this at all.
     */
    public function context(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        return [];
    }
}
