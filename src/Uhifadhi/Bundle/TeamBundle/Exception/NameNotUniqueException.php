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
 * WHERE THAT IS DIFFERS BY WHAT IS BEING NAMED, which is why this says only
 * that the clash happened and leaves the sentence to the screen. A POSITION's
 * name is unique across the whole organization — it belongs to no department,
 * so there is one Sergeant and nowhere narrower for the name to be unique in.
 * A DEPARTMENT's is unique inside its scope and nowhere else, because two
 * areas may each run an Anti-Poaching unit.
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
