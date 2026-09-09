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

namespace Uhifadhi\Bundle\AreaBundle\Exception;

/**
 * THE IDENTITY EDIT COULD NOT BE SAVED, AND THE MESSAGE IS FOR THE PERSON
 * SAVING IT.
 *
 * The one invariant an edit shares with a creation is the name: an area cannot
 * be left nameless any more than it can be born nameless. This is the type the
 * edit screen catches and prints back on the form — a sibling of
 * {@see AreaCreationException} kept separate because a "creation failed"
 * sentence raised from an edit reads as a bug rather than as advice. Anything
 * that is NOT this becomes a 500, because a bug is not advice.
 */
final class AreaIdentityException extends \RuntimeException
{
}
