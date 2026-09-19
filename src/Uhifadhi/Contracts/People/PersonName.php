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
 * SOMEBODY WHO CAN BE PICKED — an identifier, the name a picker prints, and
 * the position that tells two people of the same name apart.
 *
 * WHY THE POSITION IS HERE and not left to the facets seam: this is what a
 * chooser draws, and a chooser that had to ask a second question to label its
 * own options would be two round trips for one list. The facet seam answers
 * about people a surface already has; this one answers who there is.
 */
final readonly class PersonName
{
    public function __construct(
        public string $userUuid,
        public string $name,
        public ?string $position = null,
    ) {
        if ('' === trim($userUuid) || '' === trim($name)) {
            throw new \InvalidArgumentException('A person in a directory has an identifier and a name.');
        }
    }

    /** The name as a chooser prints it, with the position where there is one. */
    public function label(): string
    {
        return null === $this->position ? $this->name : \sprintf('%s · %s', $this->name, $this->position);
    }
}
