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

namespace Uhifadhi\Contracts\People;

/**
 * THE CONTRACT whoever owns people implements so a list somewhere else can
 * filter by role and department.
 *
 * WHY IT EXISTS AT ALL. A station's postings board draws a position title and
 * a department on every line and counts them in two dropdowns. Both facts are
 * the team bundle's; the board is the area bundle's; and the area must not
 * depend on team — a module graph where the ground depends on the people is a
 * graph where an installation cannot have the first without the second.
 *
 * WHY NOT WIDEN THE USER CONTRACT. {@see \Uhifadhi\Contracts\Entity\UserInterface}
 * is implemented by every installation that brings its own account class, and
 * two more required questions is a cost paid by everybody to serve one board.
 * A separate seam is answered by whoever has the answer and by nobody else.
 *
 * ONE CALL FOR THE WHOLE LIST. A board draws thirty lines; asking per person
 * would be thirty queries behind one card. The request is the set of
 * identifiers, and the answer is keyed by them.
 *
 * ANSWERING FOR NOBODY IS LEGITIMATE. An installation with no such concept
 * returns an empty map, and the board draws its lines without those two
 * columns rather than with two empty ones.
 *
 * TAGGED EXPLICITLY AT BOTH ENDS, as every seam in this platform is: a
 * reusable bundle is not autoconfigured, so the implementor tags its own
 * service, and an application service carries `#[AutoconfigureTag]` on its own
 * class — never on this interface, where PHP would not inherit it and the only
 * symptom would be two dropdowns that quietly went empty.
 */
interface PersonFacetProviderInterface
{
    public const string TAG = 'uhifadhi.person_facets';

    /**
     * @param list<string> $userUuids the people a surface is about to draw
     *
     * @return array<string, PersonFacet> keyed by uuid; a person left out is a person this provider knows nothing about
     */
    public function facetsFor(array $userUuids): array;
}
