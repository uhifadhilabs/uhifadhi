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
 * THE CONTRACT whoever owns people implements so that a surface somewhere
 * else can offer them to be picked.
 *
 * WHY IT EXISTS AT ALL. Posting somebody to a station means choosing a
 * person, and who the people are is not the area module's to know — the same
 * reason {@see PersonFacetProviderInterface} exists, one question further
 * back: that seam answers what a person is, this one answers who there is.
 *
 * WHY NOT READ THE USER TABLE DIRECTLY. The area can hold a posting against
 * the published user contract because Doctrine resolves it to whatever class
 * the installation named — but an interface is not a list, and ordering
 * people by name means knowing which columns their name is in. That is the
 * implementor's knowledge, and this is where they hand over the answer.
 *
 * ANSWERING FOR NOBODY IS LEGITIMATE. An installation with no directory
 * returns an empty list, and the surface says it cannot offer anybody rather
 * than drawing a chooser with nothing in it.
 *
 * TAGGED EXPLICITLY AT BOTH ENDS, as every seam in this platform is: a
 * reusable bundle is not autoconfigured, so the implementor tags its own
 * service and never this interface, where PHP would not inherit the attribute
 * and the only symptom would be a chooser that quietly went empty.
 */
interface PersonDirectoryProviderInterface
{
    public const string TAG = 'uhifadhi.person_directory';

    /**
     * @return list<PersonName> in the order a chooser should list them
     */
    public function people(): array;
}
