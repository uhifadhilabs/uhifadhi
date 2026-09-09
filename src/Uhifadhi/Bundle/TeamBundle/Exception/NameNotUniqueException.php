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

namespace Uhifadhi\Bundle\TeamBundle\Exception;

/**
 * The name is already taken WHERE IT WOULD HAVE BEEN WRITTEN.
 *
 * A position's name is unique inside its department and nowhere else, and a
 * department's inside its scope and nowhere else — two areas may each run an
 * Anti-Poaching unit, and *Ecology / Analyst* and *Protection Service / Analyst*
 * are two different jobs that share a word. So the clash is never about the name
 * alone, and the sentence that reports it has to name the place: the screens
 * word it, this only says that it happened.
 *
 * The database index is what actually refuses. This is that refusal, carried out
 * of the storage layer so a caller catches a fact about the org chart rather
 * than a driver's constraint violation.
 */
final class NameNotUniqueException extends \DomainException
{
    public function __construct(public readonly string $name, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('The name “%s” is already used where this would have filed it.', $name), 0, $previous);
    }
}
