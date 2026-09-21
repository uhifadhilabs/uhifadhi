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

namespace Uhifadhi\Bundle\AreaBundle\Access;

use Uhifadhi\Contracts\Access\Concern;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;
use Uhifadhi\Contracts\Access\ScopeKind;
use Uhifadhi\Contracts\Access\Verb;

/**
 * THE GROUND'S CONCERNS: the areas, the zones they are divided into, the
 * stations somebody is stationed at, and the assignments that put them
 * there.
 *
 * THEY OFFER ORGANIZATION OR AREA, AND NOT DEPARTMENT, because ground is
 * where it is. A department runs modules over ground; it does not own the
 * ground, so there is no sense in which a boundary belongs to Ecology and
 * not to Protection.
 *
 * WHOEVER ENFORCES A CONCERN DECLARES IT, which is why these are here and
 * not in the bundle that owns people.
 */
final readonly class AreaConcerns implements ConcernSourceInterface
{
    /** The keys, spelt once, so a gate, a door and a test cannot disagree. */
    public const string AREAS = 'areas';
    public const string ZONES = 'zones';
    public const string STATIONS = 'stations';
    public const string ASSIGNMENTS = 'assignments';

    public function declaredBy(): string
    {
        return 'Areas';
    }

    public function concerns(): iterable
    {
        $ground = [ScopeKind::Organization, ScopeKind::Area];

        yield new Concern(
            key: self::AREAS,
            label: 'Areas',
            description: 'The areas this installation manages, their boundaries and their settings.',
            verbs: [Verb::Read, Verb::Manage, Verb::Configure, Verb::Delete, Verb::Export],
            scopeKinds: $ground,
        );

        yield new Concern(
            key: self::ZONES,
            label: 'Zones',
            description: 'The zones an area is divided into, and the boundary file they are imported from.',
            verbs: [Verb::Read, Verb::Manage, Verb::Configure, Verb::Delete, Verb::Export],
            scopeKinds: $ground,
        );

        yield new Concern(
            key: self::STATIONS,
            label: 'Stations',
            description: 'The stations in an area - gates, ranger stations, headquarters, outposts - and the ground each one covers.',
            verbs: [Verb::Read, Verb::Manage, Verb::Configure, Verb::Delete],
            scopeKinds: $ground,
        );

        yield new Concern(
            key: self::ASSIGNMENTS,
            label: 'Assignments',
            description: 'Who is stationed where: assigning somebody to a station, and ending that assignment.',
            verbs: [Verb::Read, Verb::Manage, Verb::Delete],
            scopeKinds: $ground,
        );
    }
}
