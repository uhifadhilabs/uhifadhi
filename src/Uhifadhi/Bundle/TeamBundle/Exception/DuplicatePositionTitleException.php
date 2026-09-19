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
 * TWO TITLES BY ONE NAME IS ONE TITLE ENTERED TWICE.
 *
 * NOT TO BE CONFUSED WITH TWO POSITIONS SHARING A WORD, which is legal and is
 * the case the vocabulary exists to allow: `Analyst` in Ecology and `Analyst`
 * in Protection Service are two jobs. The TITLE is the word itself, offered to
 * every department, so there is one of it.
 *
 * Thrown rather than returned as a false, because every caller has to handle
 * it and a boolean is the one shape a caller can forget to look at.
 */
final class DuplicatePositionTitleException extends \RuntimeException
{
    public function __construct(public readonly string $name)
    {
        parent::__construct(\sprintf('There is already a position title called "%s".', $name));
    }
}
