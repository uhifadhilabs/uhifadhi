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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web;

use Uhifadhi\Contracts\Area\StationAction;
use Uhifadhi\Contracts\Area\StationSection;
use Uhifadhi\Contracts\Area\StationSectionRequest;
use Uhifadhi\Contracts\Area\StationSections;
use Uhifadhi\Contracts\Area\StationSectionsInterface;
use Uhifadhi\Contracts\Area\StationSurface;

/**
 * A MODULE PUTTING A SECTION ON A POST, tagged the way the roster module
 * tags its watch band. It stands in for every such contribution: the area
 * bundle never learns what it is about.
 *
 * IT SPEAKS TO BOTH SURFACES AND SAYS DIFFERENT THINGS, because that is the
 * point of the surface travelling with the question: the record answers
 * what is happening at this post, the configure card asks what should.
 */
final readonly class FakeStationSections implements StationSectionsInterface
{
    public function moduleSlug(): string
    {
        return 'patrols';
    }

    public function sectionsFor(StationSectionRequest $request): StationSections
    {
        $byStation = [];
        foreach ($request->stations as $station) {
            $byStation[$station->stationUuid] = [
                StationSurface::Record === $request->surface
                    ? new StationSection(
                        'watch',
                        'Watch and presence',
                        '@fixtures/station/_watch.html.twig',
                        ['shifts' => 'day 06-18 and night 18-06'],
                        'day & night · 2 rostered now',
                        [new StationAction('The rotation', '/modules/fake/rotation')],
                    )
                    : new StationSection(
                        'watch-settings',
                        'Watch and presence',
                        '@fixtures/station/_watch.html.twig',
                        ['shifts' => 'day 06-18 and night 18-06'],
                    ),
            ];
        }

        return new StationSections($byStation);
    }
}
