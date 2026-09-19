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
 * THE CONTRACT whoever owns stations implements so a person's own page can say
 * where they work.
 *
 * THE OTHER DIRECTION FROM {@see PersonFacetProviderInterface}, and both exist
 * for one reason: the ground and the people live in different bundles, neither
 * may depend on the other, and each has half of what the other's page prints.
 * A station's board needs a role; a person's page needs a posting. Two seams,
 * each answered by whoever has the answer.
 *
 * STANDING POSTINGS ONLY, unless a caller says otherwise — and no caller can,
 * because a person's page asks where they work TODAY. Where they worked in
 * 2024 is a history, and a history is a different page with a different
 * question.
 *
 * ONE CALL FOR THE WHOLE LIST, as everywhere: a team register draws a page of
 * people, and asking per person would be a query per row.
 *
 * TAGGED EXPLICITLY AT BOTH ENDS. Nothing autoconfigures a reusable bundle's
 * services, and an `#[AutoconfigureTag]` on this interface would be silently
 * dead — PHP does not inherit attributes from an interface, and the only
 * symptom would be every person's page quietly saying they work nowhere.
 */
interface PersonPostingProviderInterface
{
    public const string TAG = 'uhifadhi.person_postings';

    /**
     * @param list<string> $userUuids the people a surface is about to draw
     *
     * @return array<string, list<PersonPosting>> keyed by uuid; a person with no standing posting is an empty list, never a null
     */
    public function postingsFor(array $userUuids): array;
}
